<?php

/**
 * POST /api/factures-entreprises
 * Create a new facture entreprise.
 */

$user = requireAuth();

if (!verifyCsrf()) {
    jsonResponse(['error' => 'Token CSRF invalide'], 403);
}

$input = jsonInput();
$oid = orgId();

$entreprise_id = (int)($input['entreprise_id'] ?? 0);
$montant_total = (int)($input['montant_total'] ?? 0);
$montant_paye  = (int)($input['montant_paye'] ?? 0);
$date_facture  = trim($input['date_facture'] ?? '');
$date_echeance = trim($input['date_echeance'] ?? '');
$statut        = trim($input['statut'] ?? 'en_attente');
$observations  = trim($input['observations'] ?? '');

// Validation
$errors = [];
if (!$entreprise_id) $errors[] = "L'entreprise est requise";
if ($montant_total <= 0) $errors[] = 'Le montant total est requis';

if ($errors) {
    jsonResponse(['error' => implode(', ', $errors)], 422);
}

// Verify entreprise belongs to org
$stmt = db()->prepare('SELECT id FROM entreprises WHERE id = ? AND org_id = ?');
$stmt->execute([$entreprise_id, $oid]);
if (!$stmt->fetch()) {
    jsonResponse(['error' => 'Entreprise introuvable'], 404);
}

// Generate numero
$numero = generateNumero('FENT');

$stmt = db()->prepare('
    INSERT INTO factures_entreprises
        (org_id, numero, entreprise_id, montant_total, montant_paye,
         date_facture, date_echeance, statut, observations)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
');
$stmt->execute([
    $oid, $numero, $entreprise_id, $montant_total, $montant_paye,
    $date_facture ?: null, $date_echeance ?: null, $statut, $observations,
]);

jsonResponse(['ok' => true, 'id' => (int)db()->lastInsertId(), 'numero' => $numero], 201);
