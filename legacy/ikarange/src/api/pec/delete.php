<?php

/**
 * DELETE /api/pec/{id}
 * Delete a prise en charge.
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

$stmt = db()->prepare('DELETE FROM prises_en_charge WHERE id = ? AND org_id = ?');
$stmt->execute([$id, $oid]);

if ($stmt->rowCount() === 0) {
    jsonResponse(['error' => 'Prise en charge introuvable'], 404);
}

jsonResponse(['ok' => true]);
