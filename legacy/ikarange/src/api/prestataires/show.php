<?php

/**
 * GET /api/prestataires/{id}
 * Afficher un prestataire.
 */

$user = requireAuth();
$oid  = orgId();
$id   = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    jsonResponse(['error' => 'ID invalide'], 400);
}

$stmt = db()->prepare('SELECT * FROM prestataires WHERE id = ? AND org_id = ?');
$stmt->execute([$id, $oid]);
$row = $stmt->fetch();

if (!$row) {
    jsonResponse(['error' => 'Prestataire non trouvé'], 404);
}

jsonResponse($row);
