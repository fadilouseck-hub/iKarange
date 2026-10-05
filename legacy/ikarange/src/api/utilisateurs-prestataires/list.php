<?php

/**
 * GET /api/utilisateurs-prestataires
 * List all prestataire users with joined prestataire name.
 */

$user = requireAuth();
$oid = orgId();

$stmt = db()->prepare('
    SELECT up.id, up.login, up.nom_complet, up.is_active, up.created_at,
           p.nom AS prestataire_nom, p.id AS prestataire_id
    FROM utilisateurs_prestataires up
    LEFT JOIN prestataires p ON p.id = up.prestataire_id
    WHERE up.org_id = ?
    ORDER BY up.created_at DESC
');
$stmt->execute([$oid]);
$rows = $stmt->fetchAll();

jsonResponse([
    'data'  => $rows,
    'total' => count($rows),
]);
