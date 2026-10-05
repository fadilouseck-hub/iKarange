<?php

/**
 * POST /api/login
 * Authenticate admin/staff user.
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

// Rate limiting
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$cfg = $GLOBALS['appConfig']['rate_limit'];

try {
    // Clean old attempts
    $stmt = db()->prepare('DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL ? SECOND)');
    $stmt->execute([$cfg['login_window']]);

    // Count recent attempts
    $stmt = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip_address = ?');
    $stmt->execute([$ip]);
    $attempts = (int)$stmt->fetchColumn();

    if ($attempts >= $cfg['login_max']) {
        jsonResponse(['error' => 'Trop de tentatives. Réessayez dans quelques minutes.'], 429);
    }
} catch (PDOException $e) {
    // login_attempts table might not exist yet, skip rate limiting
}

// ── Try 1: Admin/Staff users ──
$stmt = db()->prepare('SELECT * FROM users WHERE login = ? AND is_active = 1');
$stmt->execute([$login]);
$user = $stmt->fetch();

if ($user && password_verify($password, $user['password_hash'])) {
    $_SESSION['user'] = [
        'id'        => (int)$user['id'],
        'org_id'    => (int)$user['org_id'],
        'login'     => $user['login'],
        'full_name' => $user['full_name'],
        'email'     => $user['email'],
        'role'      => $user['role'],
        'profile_id' => $user['profile_id'],
        'is_super'  => (int)$user['is_super'],
    ];
    session_regenerate_id(true);
    jsonResponse(['ok' => true, 'redirect' => '/', 'user_type' => 'admin']);
}

// ── Try 2: Prestataire portal users ──
$stmt = db()->prepare('
    SELECT up.*, p.nom as prestataire_nom, p.id as prest_id
    FROM utilisateurs_prestataires up
    JOIN prestataires p ON p.id = up.prestataire_id
    WHERE up.login = ? AND up.is_active = 1
');
$stmt->execute([$login]);
$prestaUser = $stmt->fetch();

if ($prestaUser && password_verify($password, $prestaUser['password_hash'])) {
    $_SESSION['prestataire_user'] = [
        'id'              => (int)$prestaUser['id'],
        'org_id'          => (int)$prestaUser['org_id'],
        'prestataire_id'  => (int)$prestaUser['prest_id'],
        'prestataire_nom' => $prestaUser['prestataire_nom'],
        'login'           => $prestaUser['login'],
        'nom_complet'     => $prestaUser['nom_complet'],
    ];
    session_regenerate_id(true);
    jsonResponse(['ok' => true, 'redirect' => '/tiers-payant', 'user_type' => 'prestataire']);
}

// ── Try 3: Adherent portal users ──
$stmt = db()->prepare('
    SELECT a.*, e.raison_sociale as entreprise_nom
    FROM adherents a
    LEFT JOIN entreprises e ON e.id = a.entreprise_id
    WHERE a.login = ? AND a.statut = "actif" AND a.login IS NOT NULL AND a.password_hash IS NOT NULL
');
$stmt->execute([$login]);
$adherent = $stmt->fetch();

if ($adherent && password_verify($password, $adherent['password_hash'])) {
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
    jsonResponse(['ok' => true, 'redirect' => '/portail-adherent', 'user_type' => 'adherent']);
}

// ── No match found ──
try {
    $stmt = db()->prepare('INSERT INTO login_attempts (ip_address) VALUES (?)');
    $stmt->execute([$ip]);
} catch (PDOException $e) {
    // skip
}
jsonResponse(['error' => 'Identifiants incorrects'], 401);
