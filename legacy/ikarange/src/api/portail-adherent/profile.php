<?php

/**
 * POST /api/portail-adherent/profile
 * Update adherent telephone.
 */

$au = requireAdherent();

if (!verifyCsrf()) {
    jsonResponse(['error' => 'Token CSRF invalide'], 403);
}

$input = jsonInput();
$telephone = trim($input['telephone'] ?? '');

$stmt = db()->prepare('UPDATE adherents SET telephone = ? WHERE id = ?');
$stmt->execute([$telephone, $au['id']]);

jsonResponse(['ok' => true]);
