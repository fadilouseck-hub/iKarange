<?php

/**
 * DELETE /api/utilisateurs-prestataires/{id}
 * Delete a prestataire user account.
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

$stmt = db()->prepare('DELETE FROM utilisateurs_prestataires WHERE id = ? AND org_id = ?');
$stmt->execute([$id, $oid]);

if ($stmt->rowCount() === 0) {
    jsonResponse(['error' => 'Utilisateur prestataire introuvable'], 404);
}

jsonResponse(['ok' => true]);
