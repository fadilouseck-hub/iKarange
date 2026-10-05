<?php

/**
 * POST /api/utilisateurs-prestataires
 * Create a new prestataire user account.
 */

$user = requireAuth();

if (!verifyCsrf()) {
    jsonResponse(['error' => 'Token CSRF invalide'], 403);
}

$input = jsonInput();
$oid = orgId();

$prestataire_id = (int)($input['prestataire_id'] ?? 0);
$login          = trim($input['login'] ?? '');
$password       = $input['password'] ?? '';
$nom_complet    = trim($input['nom_complet'] ?? '');
$is_active      = isset($input['is_active']) ? (int)(bool)$input['is_active'] : 1;

// Validation
$errors = [];
if (!$prestataire_id) $errors[] = 'Le prestataire est requis';
if (!$login)          $errors[] = 'Le login est requis';
if (!$password)       $errors[] = 'Le mot de passe est requis';

if ($errors) {
    jsonResponse(['error' => implode(', ', $errors)], 422);
}

// Check login uniqueness
$stmt = db()->prepare('SELECT id FROM utilisateurs_prestataires WHERE login = ?');
$stmt->execute([$login]);
if ($stmt->fetch()) {
    jsonResponse(['error' => 'Ce login est déjà utilisé'], 422);
}

// Check prestataire belongs to org
$stmt = db()->prepare('SELECT id FROM prestataires WHERE id = ? AND org_id = ?');
$stmt->execute([$prestataire_id, $oid]);
if (!$stmt->fetch()) {
    jsonResponse(['error' => 'Prestataire introuvable'], 404);
}

$password_hash = password_hash($password, PASSWORD_BCRYPT);

$stmt = db()->prepare('
    INSERT INTO utilisateurs_prestataires (org_id, prestataire_id, login, password_hash, nom_complet, is_active)
    VALUES (?, ?, ?, ?, ?, ?)
');
$stmt->execute([$oid, $prestataire_id, $login, $password_hash, $nom_complet, $is_active]);

jsonResponse(['ok' => true, 'id' => (int)db()->lastInsertId()], 201);
