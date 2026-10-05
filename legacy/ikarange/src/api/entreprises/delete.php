<?php

/**
 * DELETE /api/entreprises/{id}
 * Deletes an entreprise by ID.
 */

$user = requireAuth();

if (!verifyCsrf()) {
    jsonResponse(['error' => 'Token CSRF invalide'], 403);
}

$oid = orgId();
$id  = (int)($_GET['id'] ?? 0);

if (!$id) {
    jsonResponse(['error' => 'ID manquant'], 400);
}

$stmt = db()->prepare('DELETE FROM entreprises WHERE id = ? AND org_id = ?');
$stmt->execute([$id, $oid]);

jsonResponse(['ok' => true]);
