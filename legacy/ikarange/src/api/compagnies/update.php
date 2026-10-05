<?php

/**
 * POST /api/compagnies/{id}
 * Updates an existing compagnie d'assurance.
 */

$user = requireAuth();

if (!verifyCsrf()) {
    jsonResponse(['error' => 'Token CSRF invalide'], 403);
}

$input = jsonInput();
$oid   = orgId();
$id    = (int)($_GET['id'] ?? 0);

if (!$id) {
    jsonResponse(['error' => 'ID manquant'], 400);
}

// Validate required fields
if (empty(trim($input['nom'] ?? ''))) {
    jsonResponse(['error' => 'Le nom de la compagnie est obligatoire'], 422);
}

$stmt = db()->prepare("
    UPDATE compagnies_assurance SET
        nom              = ?,
        code             = ?,
        telephone        = ?,
        email            = ?,
        adresse          = ?,
        personne_contact = ?,
        actif            = ?
    WHERE id = ? AND org_id = ?
");

$stmt->execute([
    trim($input['nom']),
    trim($input['code'] ?? '') ?: null,
    trim($input['telephone'] ?? '') ?: null,
    trim($input['email'] ?? '') ?: null,
    trim($input['adresse'] ?? '') ?: null,
    trim($input['personne_contact'] ?? '') ?: null,
    isset($input['actif']) ? (int)$input['actif'] : 1,
    $id,
    $oid,
]);

jsonResponse(['ok' => true]);
