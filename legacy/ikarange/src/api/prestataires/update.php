<?php

/**
 * POST /api/prestataires/{id}
 * Mettre à jour un prestataire.
 */

$user = requireAuth();

if (!verifyCsrf()) {
    jsonResponse(['error' => 'Token CSRF invalide'], 403);
}

$oid = orgId();
$id  = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    jsonResponse(['error' => 'ID invalide'], 400);
}

// Verify existence
$stmt = db()->prepare('SELECT id FROM prestataires WHERE id = ? AND org_id = ?');
$stmt->execute([$id, $oid]);
if (!$stmt->fetch()) {
    jsonResponse(['error' => 'Prestataire non trouvé'], 404);
}

$input = jsonInput();

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
    UPDATE prestataires
    SET nom = ?, type = ?, ville = ?, adresse = ?, telephone = ?, email = ?, specialites = ?, statut = ?
    WHERE id = ? AND org_id = ?
');
$stmt->execute([$nom, $type, $ville, $adresse, $telephone, $email, $specialites, $statut, $id, $oid]);

jsonResponse(['ok' => true]);
