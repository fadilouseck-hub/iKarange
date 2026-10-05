<?php

/**
 * POST /api/assureurs/{id}
 * Update an assureur. Super-admin only.
 */

requireSuperAdmin();

if (!verifyCsrf()) {
    jsonResponse(['error' => 'Token CSRF invalide'], 403);
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    jsonResponse(['error' => 'ID requis'], 422);
}

$input = jsonInput();
$name         = trim($input['name'] ?? '');
$contactName  = trim($input['contact_name'] ?? '');
$contactEmail = trim($input['contact_email'] ?? '');
$contactPhone = trim($input['contact_phone'] ?? '');
$address      = trim($input['address'] ?? '');
$isActive     = (int)($input['is_active'] ?? 1);

if (!$name) {
    jsonResponse(['error' => 'Le nom est requis'], 422);
}

$stmt = db()->prepare('UPDATE organizations SET name = ?, contact_name = ?, contact_email = ?, contact_phone = ?, address = ?, is_active = ? WHERE id = ?');
$stmt->execute([$name, $contactName, $contactEmail, $contactPhone, $address, $isActive, $id]);

jsonResponse(['ok' => true]);
