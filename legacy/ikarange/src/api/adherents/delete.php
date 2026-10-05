<?php

/**
 * DELETE /api/adherents/{id}
 * Delete an adherent.
 */

$user = requireAuth();
$oid  = orgId();
$id   = (int)($_GET['id'] ?? 0);

if (requestMethod() !== 'DELETE') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

if (!verifyCsrf()) {
    jsonResponse(['error' => 'Token CSRF invalide'], 403);
}

if ($id <= 0) {
    jsonResponse(['error' => 'ID invalide'], 400);
}

// Check adherent exists and belongs to org
$stmt = db()->prepare('SELECT id FROM adherents WHERE id = ? AND org_id = ?');
$stmt->execute([$id, $oid]);
if (!$stmt->fetch()) {
    jsonResponse(['error' => 'Adhérent introuvable'], 404);
}

// Check for linked prises_en_charge
$stmt = db()->prepare('SELECT COUNT(*) FROM prises_en_charge WHERE adherent_id = ?');
$stmt->execute([$id]);
$pecCount = (int)$stmt->fetchColumn();

if ($pecCount > 0) {
    jsonResponse(['error' => 'Impossible de supprimer : cet adhérent a ' . $pecCount . ' prise(s) en charge associée(s).'], 409);
}

$stmt = db()->prepare('DELETE FROM adherents WHERE id = ? AND org_id = ?');
$stmt->execute([$id, $oid]);

jsonResponse(['ok' => true]);
