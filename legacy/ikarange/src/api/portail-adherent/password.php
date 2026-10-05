<?php

/**
 * POST /api/portail-adherent/password
 * Change adherent password.
 */

$au = requireAdherent();

if (!verifyCsrf()) {
    jsonResponse(['error' => 'Token CSRF invalide'], 403);
}

$input = jsonInput();
$current = $input['current_password'] ?? '';
$newPass = $input['new_password'] ?? '';

if (!$current || !$newPass) {
    jsonResponse(['error' => 'Tous les champs sont requis'], 422);
}

if (mb_strlen($newPass) < 6) {
    jsonResponse(['error' => 'Le nouveau mot de passe doit contenir au moins 6 caracteres'], 422);
}

// Verify current password
$stmt = db()->prepare('SELECT password_hash FROM adherents WHERE id = ?');
$stmt->execute([$au['id']]);
$hash = $stmt->fetchColumn();

if (!password_verify($current, $hash)) {
    jsonResponse(['error' => 'Mot de passe actuel incorrect'], 401);
}

// Update
$newHash = password_hash($newPass, PASSWORD_BCRYPT);
$stmt = db()->prepare('UPDATE adherents SET password_hash = ? WHERE id = ?');
$stmt->execute([$newHash, $au['id']]);

jsonResponse(['ok' => true]);
