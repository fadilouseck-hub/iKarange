<?php

/**
 * POST /api/adherent-login
 * Authenticate an adherent for the portal.
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

$stmt = db()->prepare('
    SELECT a.*, e.raison_sociale as entreprise_nom
    FROM adherents a
    LEFT JOIN entreprises e ON e.id = a.entreprise_id
    WHERE a.login = ? AND a.statut = "actif" AND a.login IS NOT NULL
');
$stmt->execute([$login]);
$adherent = $stmt->fetch();

if (!$adherent || !$adherent['password_hash'] || !password_verify($password, $adherent['password_hash'])) {
    jsonResponse(['error' => 'Identifiants incorrects'], 401);
}

$_SESSION['adherent_user'] = [
    'id'             => (int)$adherent['id'],
    'org_id'         => (int)$adherent['org_id'],
    'entreprise_id'  => (int)$adherent['entreprise_id'],
    'nom'            => $adherent['nom'],
    'prenom'         => $adherent['prenom'],
    'matricule'      => $adherent['matricule'],
    'entreprise_nom' => $adherent['entreprise_nom'],
];

session_regenerate_id(true);

jsonResponse(['ok' => true]);
