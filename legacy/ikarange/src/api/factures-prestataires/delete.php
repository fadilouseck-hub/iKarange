<?php

/**
 * DELETE /api/factures-prestataires/{id}
 * Delete a facture prestataire.
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

$stmt = db()->prepare('DELETE FROM factures_prestataires WHERE id = ? AND org_id = ?');
$stmt->execute([$id, $oid]);

if ($stmt->rowCount() === 0) {
    jsonResponse(['error' => 'Facture introuvable'], 404);
}

jsonResponse(['ok' => true]);
