<?php

/**
 * DELETE /api/prestataires/{id}
 * Supprimer un prestataire.
 */

$user = requireAuth();

if (!verifyCsrf()) {
    jsonResponse(['error' => 'Token CSRF invalide'], 403);
}

$oid = orgId();
$id  = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    jsonResponse(['error' => 'ID invalide'], 400);
}

// Verify existence
$stmt = db()->prepare('SELECT id FROM prestataires WHERE id = ? AND org_id = ?');
$stmt->execute([$id, $oid]);
if (!$stmt->fetch()) {
    jsonResponse(['error' => 'Prestataire non trouvé'], 404);
}

// Check for linked PEC
$stmt = db()->prepare('SELECT COUNT(*) FROM prises_en_charge WHERE prestataire_id = ?');
$stmt->execute([$id]);
$pecCount = (int)$stmt->fetchColumn();

if ($pecCount > 0) {
    jsonResponse(['error' => 'Impossible de supprimer : ce prestataire a des prises en charge associées'], 409);
}

// Check for linked invoices
$stmt = db()->prepare('SELECT COUNT(*) FROM factures_prestataires WHERE prestataire_id = ?');
$stmt->execute([$id]);
$factureCount = (int)$stmt->fetchColumn();

if ($factureCount > 0) {
    jsonResponse(['error' => 'Impossible de supprimer : ce prestataire a des factures associées'], 409);
}

// Check for linked provider users
$stmt = db()->prepare('SELECT COUNT(*) FROM utilisateurs_prestataires WHERE prestataire_id = ?');
$stmt->execute([$id]);
$userCount = (int)$stmt->fetchColumn();

if ($userCount > 0) {
    jsonResponse(['error' => 'Impossible de supprimer : ce prestataire a des utilisateurs associés'], 409);
}

$stmt = db()->prepare('DELETE FROM prestataires WHERE id = ? AND org_id = ?');
$stmt->execute([$id, $oid]);

jsonResponse(['ok' => true]);
