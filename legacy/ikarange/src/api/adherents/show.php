<?php

/**
 * GET /api/adherents/{id}
 * Return a single adherent with entreprise name.
 */

$user = requireAuth();
$oid  = orgId();
$id   = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    jsonResponse(['error' => 'ID invalide'], 400);
}

$stmt = db()->prepare('
    SELECT a.*, e.raison_sociale AS entreprise_nom
    FROM adherents a
    LEFT JOIN entreprises e ON e.id = a.entreprise_id
    WHERE a.id = ? AND a.org_id = ?
');
$stmt->execute([$id, $oid]);
$adherent = $stmt->fetch();

if (!$adherent) {
    jsonResponse(['error' => 'Adhérent introuvable'], 404);
}

jsonResponse($adherent);
