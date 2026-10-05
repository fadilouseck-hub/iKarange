<?php

/**
 * POST /api/assureurs
 * Create a new assureur (organization) + default admin user. Super-admin only.
 */

requireSuperAdmin();

if (!verifyCsrf()) {
    jsonResponse(['error' => 'Token CSRF invalide'], 403);
}

$input = jsonInput();
$name         = trim($input['name'] ?? '');
$contactName  = trim($input['contact_name'] ?? '');
$contactEmail = trim($input['contact_email'] ?? '');
$contactPhone = trim($input['contact_phone'] ?? '');
$address      = trim($input['address'] ?? '');

if (!$name) {
    jsonResponse(['error' => 'Le nom est requis'], 422);
}

$pdo = db();
$pdo->beginTransaction();

try {
    // Create organization
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));
    $stmt = $pdo->prepare('INSERT INTO organizations (name, slug, contact_name, contact_email, contact_phone, address) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$name, $slug, $contactName, $contactEmail, $contactPhone, $address]);
    $orgId = (int)$pdo->lastInsertId();

    // Create default admin user for this org
    $adminLogin = $slug . '-admin';
    $adminPass = password_hash('admin123', PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("INSERT INTO users (org_id, login, password_hash, full_name, role, is_active) VALUES (?, ?, ?, ?, 'administrateur', 1)");
    $stmt->execute([$orgId, $adminLogin, $adminPass, "Admin $name"]);

    $pdo->commit();

    jsonResponse([
        'ok' => true,
        'id' => $orgId,
        'admin_login' => $adminLogin,
        'admin_password' => 'admin123',
    ]);
} catch (PDOException $e) {
    $pdo->rollBack();
    jsonResponse(['error' => 'Erreur creation: ' . $e->getMessage()], 500);
}
