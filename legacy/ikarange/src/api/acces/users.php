<?php

/**
 * /api/acces/users
 * Handles GET (list), POST (create), POST/{id} (update), DELETE/{id} (delete)
 * for access management users.
 */

$user   = requireAuth();
$oid    = orgId();
$method = requestMethod();
$id     = (int)($_GET['id'] ?? 0);

// ── GET: List users with their profiles ──
if ($method === 'GET') {
    $stmt = db()->prepare("
        SELECT u.id, u.login, u.full_name, u.email, u.phone, u.role,
               u.profile_id, u.is_active, u.notes,
               p.name AS profile_name, p.permissions AS profile_permissions
        FROM users u
        LEFT JOIN access_profiles p ON p.id = u.profile_id
        WHERE u.org_id = ?
        ORDER BY u.full_name ASC
    ");
    $stmt->execute([$oid]);
    $rows = $stmt->fetchAll();

    // Decode permissions JSON for each user
    foreach ($rows as &$row) {
        $row['profile_permissions'] = $row['profile_permissions']
            ? json_decode($row['profile_permissions'], true)
            : [];
    }
    unset($row);

    jsonResponse([
        'data'  => $rows,
        'total' => count($rows),
    ]);
}

// ── Require CSRF for write operations ──
if (!verifyCsrf()) {
    jsonResponse(['error' => 'Token CSRF invalide'], 403);
}

$input = jsonInput();

// ── POST (no id): Create user ──
if ($method === 'POST' && !$id) {
    if (empty(trim($input['full_name'] ?? ''))) {
        jsonResponse(['error' => 'Le nom complet est obligatoire'], 422);
    }
    if (empty(trim($input['login'] ?? ''))) {
        jsonResponse(['error' => "L'identifiant est obligatoire"], 422);
    }
    if (empty($input['password'] ?? '')) {
        jsonResponse(['error' => 'Le mot de passe est obligatoire'], 422);
    }

    // Check login uniqueness within org
    $stmt = db()->prepare('SELECT COUNT(*) FROM users WHERE org_id = ? AND login = ?');
    $stmt->execute([$oid, trim($input['login'])]);
    if ((int)$stmt->fetchColumn() > 0) {
        jsonResponse(['error' => 'Cet identifiant est déjà utilisé'], 422);
    }

    $stmt = db()->prepare("
        INSERT INTO users
            (org_id, full_name, login, password_hash, email, phone, role, profile_id, notes, is_active)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $oid,
        trim($input['full_name']),
        trim($input['login']),
        password_hash($input['password'], PASSWORD_DEFAULT),
        trim($input['email'] ?? '') ?: null,
        trim($input['phone'] ?? '') ?: null,
        $input['role'] ?? 'gestionnaire',
        !empty($input['profile_id']) ? (int)$input['profile_id'] : null,
        trim($input['notes'] ?? '') ?: null,
        isset($input['is_active']) ? (int)$input['is_active'] : 1,
    ]);

    jsonResponse([
        'ok' => true,
        'id' => (int)db()->lastInsertId(),
    ]);
}

// ── POST (with id): Update user ──
if ($method === 'POST' && $id) {
    if (empty(trim($input['full_name'] ?? ''))) {
        jsonResponse(['error' => 'Le nom complet est obligatoire'], 422);
    }

    // Check login uniqueness (excluding current user)
    if (!empty(trim($input['login'] ?? ''))) {
        $stmt = db()->prepare('SELECT COUNT(*) FROM users WHERE org_id = ? AND login = ? AND id != ?');
        $stmt->execute([$oid, trim($input['login']), $id]);
        if ((int)$stmt->fetchColumn() > 0) {
            jsonResponse(['error' => 'Cet identifiant est déjà utilisé'], 422);
        }
    }

    $fields = "
        full_name  = ?,
        login      = ?,
        email      = ?,
        phone      = ?,
        role       = ?,
        profile_id = ?,
        notes      = ?,
        is_active  = ?
    ";
    $params = [
        trim($input['full_name']),
        trim($input['login'] ?? ''),
        trim($input['email'] ?? '') ?: null,
        trim($input['phone'] ?? '') ?: null,
        $input['role'] ?? 'gestionnaire',
        !empty($input['profile_id']) ? (int)$input['profile_id'] : null,
        trim($input['notes'] ?? '') ?: null,
        isset($input['is_active']) ? (int)$input['is_active'] : 1,
    ];

    if (!empty($input['password'])) {
        $fields .= ", password_hash = ?";
        $params[] = password_hash($input['password'], PASSWORD_DEFAULT);
    }

    $params[] = $id;
    $params[] = $oid;

    $stmt = db()->prepare("UPDATE users SET $fields WHERE id = ? AND org_id = ?");
    $stmt->execute($params);

    jsonResponse(['ok' => true]);
}

// ── DELETE: Delete user ──
if ($method === 'DELETE' && $id) {
    $stmt = db()->prepare('DELETE FROM users WHERE id = ? AND org_id = ?');
    $stmt->execute([$id, $oid]);

    jsonResponse(['ok' => true]);
}

jsonResponse(['error' => 'Méthode non autorisée'], 405);
