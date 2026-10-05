<?php

/**
 * POST /api/compagnies
 * Creates a new compagnie d'assurance.
 */

$user = requireAuth();

if (!verifyCsrf()) {
    jsonResponse(['error' => 'Token CSRF invalide'], 403);
}

$input = jsonInput();
$oid   = orgId();

// Validate required fields
if (empty(trim($input['nom'] ?? ''))) {
    jsonResponse(['error' => 'Le nom de la compagnie est obligatoire'], 422);
}

$stmt = db()->prepare("
    INSERT INTO compagnies_assurance
        (org_id, nom, code, telephone, email, adresse, personne_contact, actif)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt->execute([
    $oid,
    trim($input['nom']),
    trim($input['code'] ?? '') ?: null,
    trim($input['telephone'] ?? '') ?: null,
    trim($input['email'] ?? '') ?: null,
    trim($input['adresse'] ?? '') ?: null,
    trim($input['personne_contact'] ?? '') ?: null,
    isset($input['actif']) ? (int)$input['actif'] : 1,
]);

jsonResponse([
    'ok' => true,
    'id' => (int)db()->lastInsertId(),
]);
