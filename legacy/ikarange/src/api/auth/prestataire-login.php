<?php

/**
 * POST /api/prestataire-login
 * Authenticate prestataire user for tiers payant portal.
 */

if (requestMethod() !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

if (!verifyCsrf()) {
    jsonResponse(['error' => 'Token CSRF invalide'], 403);
}

$input = jsonInput();
$login    = trim($input['login'] ?? '');
$password = $input['password'] ?? '';

if (!$login || !$password) {
    jsonResponse(['error' => 'Identifiant et mot de passe requis'], 422);
}

// Find prestataire user
$stmt = db()->prepare('
    SELECT up.*, p.nom as prestataire_nom, p.id as prest_id
    FROM utilisateurs_prestataires up
    JOIN prestataires p ON p.id = up.prestataire_id
    WHERE up.login = ? AND up.is_active = 1
');
$stmt->execute([$login]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    jsonResponse(['error' => 'Identifiants incorrects'], 401);
}

// Store prestataire session
$_SESSION['prestataire_user'] = [
    'id'              => (int)$user['id'],
    'org_id'          => (int)$user['org_id'],
    'prestataire_id'  => (int)$user['prest_id'],
    'prestataire_nom' => $user['prestataire_nom'],
    'login'           => $user['login'],
    'nom_complet'     => $user['nom_complet'],
];

session_regenerate_id(true);

jsonResponse(['ok' => true, 'redirect' => '/tiers-payant']);
