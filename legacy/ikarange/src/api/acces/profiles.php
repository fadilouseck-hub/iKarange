<?php

/**
 * /api/acces/profiles
 * Handles GET (list), POST (create), POST/{id} (update), DELETE/{id} (delete)
 * for access profiles.
 */

$user   = requireAuth();
$oid    = orgId();
$method = requestMethod();
$id     = (int)($_GET['id'] ?? 0);

// ── GET: List profiles with user count ──
if ($method === 'GET') {
    $stmt = db()->prepare("
        SELECT p.*, COUNT(u.id) AS user_count
        FROM access_profiles p
        LEFT JOIN users u ON u.profile_id = p.id AND u.org_id = p.org_id
        WHERE p.org_id = ?
        GROUP BY p.id
        ORDER BY p.name ASC
    ");
    $stmt->execute([$oid]);
    $rows = $stmt->fetchAll();

    // Decode permissions JSON
    foreach ($rows as &$row) {
        $row['permissions'] = $row['permissions']
            ? json_decode($row['permissions'], true)
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

// ── POST (no id): Create profile ──
if ($method === 'POST' && !$id) {
    if (empty(trim($input['name'] ?? ''))) {
        jsonResponse(['error' => 'Le nom du profil est obligatoire'], 422);
    }

    $permissions = isset($input['permissions']) && is_array($input['permissions'])
        ? json_encode($input['permissions'])
        : '[]';

    $stmt = db()->prepare("
        INSERT INTO access_profiles (org_id, name, description, permissions, is_active)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $oid,
        trim($input['name']),
        trim($input['description'] ?? '') ?: null,
        $permissions,
        isset($input['is_active']) ? (int)$input['is_active'] : 1,
    ]);

    jsonResponse([
        'ok' => true,
        'id' => (int)db()->lastInsertId(),
    ]);
}

// ── POST (with id): Update profile ──
if ($method === 'POST' && $id) {
    if (empty(trim($input['name'] ?? ''))) {
        jsonResponse(['error' => 'Le nom du profil est obligatoire'], 422);
    }

    $permissions = isset($input['permissions']) && is_array($input['permissions'])
        ? json_encode($input['permissions'])
        : '[]';

    $stmt = db()->prepare("
        UPDATE access_profiles
        SET name = ?, description = ?, permissions = ?, is_active = ?
        WHERE id = ? AND org_id = ?
    ");
    $stmt->execute([
        trim($input['name']),
        trim($input['description'] ?? '') ?: null,
        $permissions,
        isset($input['is_active']) ? (int)$input['is_active'] : 1,
        $id,
        $oid,
    ]);

    jsonResponse(['ok' => true]);
}

// ── DELETE: Delete profile (check no users linked) ──
if ($method === 'DELETE' && $id) {
    $stmt = db()->prepare('SELECT COUNT(*) FROM users WHERE profile_id = ? AND org_id = ?');
    $stmt->execute([$id, $oid]);
    $linkedUsers = (int)$stmt->fetchColumn();

    if ($linkedUsers > 0) {
        jsonResponse([
            'error' => 'Impossible de supprimer ce profil : ' . $linkedUsers . ' utilisateur(s) y sont rattaché(s)'
        ], 422);
    }

    $stmt = db()->prepare('DELETE FROM access_profiles WHERE id = ? AND org_id = ?');
    $stmt->execute([$id, $oid]);

    jsonResponse(['ok' => true]);
}

jsonResponse(['error' => 'Méthode non autorisée'], 405);
