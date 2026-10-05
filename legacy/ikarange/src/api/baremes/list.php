<?php

/**
 * GET /api/baremes?entreprise_id={id}
 * Returns all bareme rows for a given entreprise.
 */

$user = requireAuth();
$oid  = orgId();

$entreprise_id = (int)($_GET['entreprise_id'] ?? 0);
if (!$entreprise_id) {
    jsonResponse(['error' => 'entreprise_id requis'], 400);
}

// Verify entreprise belongs to org
$stmt = db()->prepare('SELECT id FROM entreprises WHERE id = ? AND org_id = ?');
$stmt->execute([$entreprise_id, $oid]);
if (!$stmt->fetch()) {
    jsonResponse(['error' => 'Entreprise introuvable'], 404);
}

$stmt = db()->prepare('
    SELECT id, type_acte, libelle, taux_couverture, plafond_acte,
           periode_plafond, plafond_par, is_active
    FROM baremes
    WHERE org_id = ? AND entreprise_id = ?
    ORDER BY id
');
$stmt->execute([$oid, $entreprise_id]);

jsonResponse(['data' => $stmt->fetchAll()]);
