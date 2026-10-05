<?php

/**
 * POST /api/entreprises
 * Creates a new entreprise.
 */

$user = requireAuth();

if (!verifyCsrf()) {
    jsonResponse(['error' => 'Token CSRF invalide'], 403);
}

$input = jsonInput();
$oid   = orgId();

// Validate required fields
if (empty(trim($input['raison_sociale'] ?? ''))) {
    jsonResponse(['error' => 'La raison sociale est obligatoire'], 422);
}

$stmt = db()->prepare("
    INSERT INTO entreprises
        (org_id, raison_sociale, ninea, telephone, email, secteur_activite,
         adresse, nombre_employes, taux_cotisation, taux_prise_en_charge,
         date_adhesion, statut)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt->execute([
    $oid,
    trim($input['raison_sociale']),
    trim($input['ninea'] ?? '') ?: null,
    trim($input['telephone'] ?? '') ?: null,
    trim($input['email'] ?? '') ?: null,
    trim($input['secteur_activite'] ?? '') ?: null,
    trim($input['adresse'] ?? '') ?: null,
    (int)($input['nombre_employes'] ?? 0),
    (float)($input['taux_cotisation'] ?? 0),
    (float)($input['taux_prise_en_charge'] ?? 80),
    !empty($input['date_adhesion']) ? $input['date_adhesion'] : null,
    $input['statut'] ?? 'actif',
]);

jsonResponse([
    'ok' => true,
    'id' => (int)db()->lastInsertId(),
]);
