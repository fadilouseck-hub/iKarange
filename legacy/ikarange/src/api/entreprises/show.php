<?php

/**
 * GET /api/entreprises/{id}
 * Returns a single entreprise by ID.
 */

$user = requireAuth();
$oid  = orgId();
$id   = (int)($_GET['id'] ?? 0);

if (!$id) {
    jsonResponse(['error' => 'ID manquant'], 400);
}

$stmt = db()->prepare('SELECT * FROM entreprises WHERE id = ? AND org_id = ?');
$stmt->execute([$id, $oid]);
$row = $stmt->fetch();

if (!$row) {
    jsonResponse(['error' => 'Entreprise introuvable'], 404);
}

jsonResponse($row);
