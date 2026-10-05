<?php

/**
 * GET /api/rapports/prestations
 * Historique des prestations / consommations.
 * Params: ?entreprise_id=, ?adherent_id=, ?type_acte=, ?statut=, ?date_debut=, ?date_fin=, ?format=csv
 */

$user = requireAuth();
$oid  = orgId();

$where = ['p.org_id = ?'];
$params = [$oid];

if (!empty($_GET['entreprise_id'])) {
    $where[] = 'a.entreprise_id = ?';
    $params[] = (int)$_GET['entreprise_id'];
}
if (!empty($_GET['adherent_id'])) {
    $where[] = 'p.adherent_id = ?';
    $params[] = (int)$_GET['adherent_id'];
}
if (!empty($_GET['type_acte'])) {
    $where[] = 'p.type_acte = ?';
    $params[] = $_GET['type_acte'];
}
if (!empty($_GET['statut'])) {
    $where[] = 'p.statut = ?';
    $params[] = $_GET['statut'];
}
if (!empty($_GET['date_debut'])) {
    $where[] = 'p.date_soins >= ?';
    $params[] = $_GET['date_debut'];
}
if (!empty($_GET['date_fin'])) {
    $where[] = 'p.date_soins <= ?';
    $params[] = $_GET['date_fin'];
}

$whereStr = implode(' AND ', $where);

$stmt = db()->prepare("
    SELECT p.numero, p.date_soins, p.type_acte, p.sous_type_acte, p.motif,
           p.montant_total, p.taux_couverture, p.part_ipm, p.part_adherent,
           p.statut, p.observations,
           a.nom as adherent_nom, a.prenom as adherent_prenom, a.matricule,
           e.raison_sociale as entreprise,
           pr.nom as prestataire_nom
    FROM prises_en_charge p
    LEFT JOIN adherents a ON a.id = p.adherent_id
    LEFT JOIN entreprises e ON e.id = a.entreprise_id
    LEFT JOIN prestataires pr ON pr.id = p.prestataire_id
    WHERE $whereStr
    ORDER BY p.date_soins DESC
    LIMIT 5000
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Summary
$totalMontant = array_sum(array_column($rows, 'montant_total'));
$totalIpm = array_sum(array_column($rows, 'part_ipm'));
$totalAdherent = array_sum(array_column($rows, 'part_adherent'));

if (($_GET['format'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="etat-prestations-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($out, ['Numero', 'Date soins', 'Type acte', 'Sous-type', 'Adherent', 'Matricule', 'Entreprise', 'Prestataire', 'Motif', 'Montant total', 'Taux %', 'Part IPM', 'Part Adherent', 'Statut', 'Observations'], ';');
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['numero'], $r['date_soins'], $r['type_acte'], $r['sous_type_acte'],
            $r['adherent_nom'] . ' ' . $r['adherent_prenom'], $r['matricule'],
            $r['entreprise'], $r['prestataire_nom'], $r['motif'],
            $r['montant_total'], $r['taux_couverture'], $r['part_ipm'],
            $r['part_adherent'], $r['statut'], $r['observations']
        ], ';');
    }
    fclose($out);
    exit;
}

jsonResponse([
    'summary' => [
        'total_pec' => count($rows),
        'montant_total' => (int)$totalMontant,
        'total_ipm' => (int)$totalIpm,
        'total_adherent' => (int)$totalAdherent,
    ],
    'data' => $rows,
]);
