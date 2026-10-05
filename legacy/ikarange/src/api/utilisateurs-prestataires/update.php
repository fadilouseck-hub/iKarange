<?php

/**
 * POST /api/utilisateurs-prestataires/{id}
 * Update an existing prestataire user account.
 */

$user = requireAuth();

if (!verifyCsrf()) {
    jsonResponse(['error' => 'Token CSRF invalide'], 403);
}

$id  = (int)($_GET['id'] ?? 0);
$oid = orgId();

if (!$id) {
    jsonResponse(['error' => 'ID requis'], 400);
}

// Verify record exists and belongs to org
$stmt = db()->prepare('SELECT * FROM utilisateurs_prestataires WHERE id = ? AND org_id = ?');
$stmt->execute([$id, $oid]);
$existing = $stmt->fetch();

if (!$existing) {
    jsonResponse(['error' => 'Utilisateur prestataire introuvable'], 404);
}

$input = jsonInput();

$prestataire_id = (int)($input['prestataire_id'] ?? $existing['prestataire_id']);
$login          = trim($input['login'] ?? $existing['login']);
$nom_complet    = trim($input['nom_complet'] ?? $existing['nom_complet']);
$is_active      = isset($input['is_active']) ? (int)(bool)$input['is_active'] : (int)$existing['is_active'];
$password       = $input['password'] ?? '';

// Check login uniqueness if changed
if ($login !== $existing['login']) {
    $stmt = db()->prepare('SELECT id FROM utilisateurs_prestataires WHERE login = ? AND id != ?');
    $stmt->execute([$login, $id]);
    if ($stmt->fetch()) {
        jsonResponse(['error' => 'Ce login est déjà utilisé'], 422);
    }
}

// Check prestataire belongs to org
$stmt = db()->prepare('SELECT id FROM prestataires WHERE id = ? AND org_id = ?');
$stmt->execute([$prestataire_id, $oid]);
if (!$stmt->fetch()) {
    jsonResponse(['error' => 'Prestataire introuvable'], 404);
}

// Update with or without password
if ($password) {
    $password_hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = db()->prepare('
        UPDATE utilisateurs_prestataires
        SET prestataire_id = ?, login = ?, password_hash = ?, nom_complet = ?, is_active = ?
        WHERE id = ? AND org_id = ?
    ');
    $stmt->execute([$prestataire_id, $login, $password_hash, $nom_complet, $is_active, $id, $oid]);
} else {
    $stmt = db()->prepare('
        UPDATE utilisateurs_prestataires
        SET prestataire_id = ?, login = ?, nom_complet = ?, is_active = ?
        WHERE id = ? AND org_id = ?
    ');
    $stmt->execute([$prestataire_id, $login, $nom_complet, $is_active, $id, $oid]);
}

jsonResponse(['ok' => true]);
