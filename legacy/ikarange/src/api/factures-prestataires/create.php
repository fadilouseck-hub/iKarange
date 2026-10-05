<?php

/**
 * POST /api/factures-prestataires
 * Create a new facture prestataire.
 */

$user = requireAuth();

if (!verifyCsrf()) {
    jsonResponse(['error' => 'Token CSRF invalide'], 403);
}

$input = jsonInput();
$oid = orgId();

$prestataire_id = (int)($input['prestataire_id'] ?? 0);
$montant_total  = (int)($input['montant_total'] ?? 0);
$montant_paye   = (int)($input['montant_paye'] ?? 0);
$date_facture   = trim($input['date_facture'] ?? '');
$date_echeance  = trim($input['date_echeance'] ?? '');
$statut         = trim($input['statut'] ?? 'en_attente');
$observations   = trim($input['observations'] ?? '');

// Validation
$errors = [];
if (!$prestataire_id) $errors[] = 'Le prestataire est requis';
if ($montant_total <= 0) $errors[] = 'Le montant total est requis';

if ($errors) {
    jsonResponse(['error' => implode(', ', $errors)], 422);
}

// Verify prestataire belongs to org
$stmt = db()->prepare('SELECT id FROM prestataires WHERE id = ? AND org_id = ?');
$stmt->execute([$prestataire_id, $oid]);
if (!$stmt->fetch()) {
    jsonResponse(['error' => 'Prestataire introuvable'], 404);
}

// Generate numero
$numero = generateNumero('FPRS');

$stmt = db()->prepare('
    INSERT INTO factures_prestataires
        (org_id, numero, prestataire_id, montant_total, montant_paye,
         date_facture, date_echeance, statut, observations)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
');
$stmt->execute([
    $oid, $numero, $prestataire_id, $montant_total, $montant_paye,
    $date_facture ?: null, $date_echeance ?: null, $statut, $observations,
]);

jsonResponse(['ok' => true, 'id' => (int)db()->lastInsertId(), 'numero' => $numero], 201);
