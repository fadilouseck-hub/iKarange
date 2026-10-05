<?php

/**
 * GET /api/adherents/template
 * Downloads a CSV template for bulk adherent import.
 */

$user = requireAuth();
$oid  = orgId();

// Fetch entreprise names for reference
$stmt = db()->prepare('SELECT raison_sociale FROM entreprises WHERE org_id = ? ORDER BY raison_sociale');
$stmt->execute([$oid]);
$entreprises = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Column headers
$headers = [
    'Nom',
    'Prenom',
    'Entreprise',
    'Sexe',
    'Date de naissance',
    'Telephone',
    'Categorie',
    'Plafond annuel',
    'Date adhesion',
    'Statut',
];

// Example rows
$examples = [
    ['Diallo', 'Mamadou', $entreprises[0] ?? 'Nom Entreprise', 'masculin', '1990-05-15', '+222 36 12 34 56', 'titulaire', '500000', '2025-01-01', 'actif'],
    ['Ba', 'Aissatou', $entreprises[0] ?? 'Nom Entreprise', 'feminin', '1985-11-20', '+222 46 78 90 12', 'conjoint', '300000', '2025-01-01', 'actif'],
];

// Instructions row
$instructions = [
    '* Requis',
    '* Requis',
    '* Raison sociale exacte',
    'masculin ou feminin',
    'Format: AAAA-MM-JJ',
    'Optionnel',
    'titulaire / conjoint / enfant',
    'Montant en F CFA',
    'Format: AAAA-MM-JJ',
    'actif / inactif / suspendu',
];

// Build CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="modele-import-adherents.csv"');
header('Cache-Control: no-cache');

// UTF-8 BOM for Excel compatibility
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');

// Write instructions row
fputcsv($out, $instructions, ';');

// Write headers
fputcsv($out, $headers, ';');

// Write example rows
foreach ($examples as $row) {
    fputcsv($out, $row, ';');
}

// If there are entreprises, add a blank line then list them as reference
if (!empty($entreprises)) {
    fputcsv($out, [], ';');
    fputcsv($out, ['--- Entreprises disponibles ---'], ';');
    foreach ($entreprises as $e) {
        fputcsv($out, [$e], ';');
    }
}

fclose($out);
exit;
