<?php

/**
 * POST /api/factures-prestataires/{id}
 * Update an existing facture prestataire.
 */

$user = requireAuth();

if (!verifyCsrf()) {
    jsonResponse(['error' => 'Token CSRF invalide'], 403);
}

$id  = (int)($_GET['id'] ?? 0);
$oid = orgId();

if (!$id) {
    jsonResponse(['error' => 'ID requis'], 400);
}

// Verify record exists and belongs to org
$stmt = db()->prepare('SELECT * FROM factures_prestataires WHERE id = ? AND org_id = ?');
$stmt->execute([$id, $oid]);
$existing = $stmt->fetch();

if (!$existing) {
    jsonResponse(['error' => 'Facture introuvable'], 404);
}

$input = jsonInput();

$prestataire_id = (int)($input['prestataire_id'] ?? $existing['prestataire_id']);
$montant_total  = (int)($input['montant_total'] ?? $existing['montant_total']);
$montant_paye   = (int)($input['montant_paye'] ?? $existing['montant_paye']);
$date_facture   = trim($input['date_facture'] ?? ($existing['date_facture'] ?? ''));
$date_echeance  = trim($input['date_echeance'] ?? ($existing['date_echeance'] ?? ''));
$statut         = trim($input['statut'] ?? $existing['statut']);
$observations   = trim($input['observations'] ?? ($existing['observations'] ?? ''));

$stmt = db()->prepare('
    UPDATE factures_prestataires
    SET prestataire_id = ?, montant_total = ?, montant_paye = ?,
        date_facture = ?, date_echeance = ?, statut = ?, observations = ?
    WHERE id = ? AND org_id = ?
');
$stmt->execute([
    $prestataire_id, $montant_total, $montant_paye,
    $date_facture ?: null, $date_echeance ?: null, $statut, $observations,
    $id, $oid,
]);

jsonResponse(['ok' => true]);
