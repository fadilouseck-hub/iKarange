<?php

/**
 * POST /api/factures-entreprises/generate
 * Auto-generate factures from approved PEC grouped by enterprise within a date range.
 */

$user = requireAuth();

if (!verifyCsrf()) {
    jsonResponse(['error' => 'Token CSRF invalide'], 403);
}

$input = jsonInput();
$oid = orgId();

$date_debut    = trim($input['date_debut'] ?? '');
$date_fin      = trim($input['date_fin'] ?? '');
$entreprise_id = (int)($input['entreprise_id'] ?? 0);

// Validation
if (!$date_debut || !$date_fin) {
    jsonResponse(['error' => 'Les dates de début et de fin sont requises'], 422);
}

// Build query to group approved PEC by enterprise (via adherent -> entreprise)
$where = [
    'pec.org_id = ?',
    'pec.statut = ?',
    'pec.date_soins >= ?',
    'pec.date_soins <= ?',
];
$params = [$oid, 'approuvee', $date_debut, $date_fin];

if ($entreprise_id) {
    $where[] = 'a.entreprise_id = ?';
    $params[] = $entreprise_id;
}

$whereClause = implode(' AND ', $where);

$stmt = db()->prepare("
    SELECT a.entreprise_id,
           SUM(pec.part_ipm) AS total_part_ipm,
           COUNT(*) AS nb_pec
    FROM prises_en_charge pec
    LEFT JOIN adherents a ON a.id = pec.adherent_id
    WHERE $whereClause
      AND a.entreprise_id IS NOT NULL
    GROUP BY a.entreprise_id
    HAVING total_part_ipm > 0
");
$stmt->execute($params);
$groups = $stmt->fetchAll();

$count = 0;
$today = date('Y-m-d');

foreach ($groups as $group) {
    $numero = generateNumero('FENT');

    $stmt = db()->prepare('
        INSERT INTO factures_entreprises
            (org_id, numero, entreprise_id, montant_total, montant_paye,
             date_facture, date_echeance, statut, observations)
        VALUES (?, ?, ?, ?, 0, ?, NULL, ?, ?)
    ');
    $stmt->execute([
        $oid,
        $numero,
        $group['entreprise_id'],
        (int)$group['total_part_ipm'],
        $today,
        'en_attente',
        'Facture auto-générée pour la période du ' . $date_debut . ' au ' . $date_fin . ' (' . $group['nb_pec'] . ' PEC)',
    ]);

    $count++;
}

jsonResponse(['ok' => true, 'count' => $count]);
