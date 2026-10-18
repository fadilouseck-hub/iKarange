<?php

/**
 * Shared guard for the "Applications mobiles" back-office API: signed-in staff with the module permission.
 * Data is always limited to members of the current organization.
 */

$user = requireAuth();
if (!hasPermission('mobile-apps')) {
    jsonResponse(['error' => 'Accès refusé'], 403);
}
if (requestMethod() !== 'GET' && !verifyCsrf()) {
    jsonResponse(['error' => 'Token CSRF invalide'], 403);
}
$oid = orgId();
