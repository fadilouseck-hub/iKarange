<?php

/**
 * POST /api/switch-org
 * Switch super-admin's active org context.
 */

requireSuperAdmin();

if (!verifyCsrf()) {
    jsonResponse(['error' => 'Token CSRF invalide'], 403);
}

$input = jsonInput();
$orgId = (int)($input['org_id'] ?? 0);

if ($orgId === 0) {
    // Reset to own org
    unset($_SESSION['switch_org_id']);
} else {
    // Verify org exists
    $stmt = db()->prepare('SELECT id FROM organizations WHERE id = ?');
    $stmt->execute([$orgId]);
    if (!$stmt->fetch()) {
        jsonResponse(['error' => 'Organisation introuvable'], 404);
    }
    $_SESSION['switch_org_id'] = $orgId;
}

jsonResponse(['ok' => true, 'org_id' => orgId()]);
