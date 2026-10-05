<?php

/**
 * GET /api/rapports/beneficiaires
 * Etat des beneficiaires (assures et ayants droits).
 * Params: ?entreprise_id=, ?statut=, ?categorie=, ?format=csv
 */

$user = requireAuth();
$oid  = orgId();

$where = ['a.org_id = ?'];
$params = [$oid];

if (!empty($_GET['entreprise_id'])) {
    $where[] = 'a.entreprise_id = ?';
    $params[] = (int)$_GET['entreprise_id'];
}
if (!empty($_GET['statut'])) {
    $where[] = 'a.statut = ?';
    $params[] = $_GET['statut'];
}
if (!empty($_GET['categorie'])) {
    $where[] = 'a.categorie = ?';
    $params[] = $_GET['categorie'];
}

$whereStr = implode(' AND ', $where);

$stmt = db()->prepare("
    SELECT a.matricule, a.nom, a.prenom, a.sexe, a.date_naissance, a.telephone,
           a.categorie, a.plafond_annuel, a.date_adhesion, a.statut,
           e.raison_sociale as entreprise
    FROM adherents a
    LEFT JOIN entreprises e ON e.id = a.entreprise_id
    WHERE $whereStr
    ORDER BY e.raison_sociale, a.nom, a.prenom
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Summary
$total = count($rows);
$actifs = count(array_filter($rows, fn($r) => $r['statut'] === 'actif'));
$titulaires = count(array_filter($rows, fn($r) => $r['categorie'] === 'titulaire'));
$ayantsDroits = $total - $titulaires;

if (($_GET['format'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="etat-beneficiaires-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM
    fputcsv($out, ['Matricule', 'Nom', 'Prenom', 'Sexe', 'Date naissance', 'Telephone', 'Categorie', 'Plafond annuel', 'Date adhesion', 'Statut', 'Entreprise'], ';');
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['matricule'], $r['nom'], $r['prenom'], $r['sexe'],
            $r['date_naissance'], $r['telephone'], $r['categorie'],
            $r['plafond_annuel'], $r['date_adhesion'], $r['statut'], $r['entreprise']
        ], ';');
    }
    fclose($out);
    exit;
}

jsonResponse([
    'summary' => [
        'total' => $total,
        'actifs' => $actifs,
        'titulaires' => $titulaires,
        'ayants_droits' => $ayantsDroits,
    ],
    'data' => $rows,
]);
