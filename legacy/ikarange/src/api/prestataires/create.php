<?php

/**
 * POST /api/prestataires
 * Créer un nouveau prestataire.
 */

$user = requireAuth();

if (!verifyCsrf()) {
    jsonResponse(['error' => 'Token CSRF invalide'], 403);
}

$input = jsonInput();
$oid   = orgId();

$nom          = trim($input['nom'] ?? '');
$type         = trim($input['type'] ?? '');
$ville        = trim($input['ville'] ?? '');
$adresse      = trim($input['adresse'] ?? '');
$telephone    = trim($input['telephone'] ?? '');
$email        = trim($input['email'] ?? '');
$specialites  = trim($input['specialites'] ?? '');
$statut       = trim($input['statut'] ?? 'agree');

// Validation
if ($nom === '') {
    jsonResponse(['error' => 'Le nom est requis'], 422);
}

$typesValides = ['clinique', 'hopital', 'pharmacie', 'laboratoire', 'centre_imagerie', 'dentiste'];
if (!in_array($type, $typesValides, true)) {
    jsonResponse(['error' => 'Le type est invalide'], 422);
}

$statutsValides = ['agree', 'suspendu', 'resilie'];
if (!in_array($statut, $statutsValides, true)) {
    $statut = 'agree';
}

$stmt = db()->prepare('
    INSERT INTO prestataires (org_id, nom, type, ville, adresse, telephone, email, specialites, statut)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
');
$stmt->execute([$oid, $nom, $type, $ville, $adresse, $telephone, $email, $specialites, $statut]);

$id = (int)db()->lastInsertId();

jsonResponse(['ok' => true, 'id' => $id], 201);
