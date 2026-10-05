<?php

/**
 * POST /api/adherents/{id}
 * Update an existing adherent.
 * Accepts multipart/form-data (for photo upload) or JSON.
 */

$user = requireAuth();
$oid  = orgId();
$id   = (int)($_GET['id'] ?? 0);

if (requestMethod() !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

if (!verifyCsrf()) {
    jsonResponse(['error' => 'Token CSRF invalide'], 403);
}

if ($id <= 0) {
    jsonResponse(['error' => 'ID invalide'], 400);
}

// Check adherent exists and belongs to org
$stmt = db()->prepare('SELECT id, photo FROM adherents WHERE id = ? AND org_id = ?');
$stmt->execute([$id, $oid]);
$existing = $stmt->fetch();

if (!$existing) {
    jsonResponse(['error' => 'Adhérent introuvable'], 404);
}

// Support both JSON and FormData
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (str_contains($contentType, 'multipart/form-data') || str_contains($contentType, 'application/x-www-form-urlencoded')) {
    $input = $_POST;
} else {
    $input = jsonInput();
}

$nom          = trim($input['nom'] ?? '');
$prenom       = trim($input['prenom'] ?? '');
$entrepriseId = (int)($input['entreprise_id'] ?? 0);
$sexe         = $input['sexe'] ?? 'masculin';
$dateNaissance = $input['date_naissance'] ?? null;
$telephone    = trim($input['telephone'] ?? '');
$categorie    = $input['categorie'] ?? 'titulaire';
$plafond      = (int)($input['plafond_annuel'] ?? 0);
$dateAdhesion = $input['date_adhesion'] ?? null;
$statut       = $input['statut'] ?? 'actif';

// Validation
if ($nom === '' || $prenom === '') {
    jsonResponse(['error' => 'Nom et prénom sont requis'], 422);
}

if ($entrepriseId <= 0) {
    jsonResponse(['error' => 'Entreprise est requise'], 422);
}

// Verify entreprise exists and belongs to org
$stmt = db()->prepare('SELECT id FROM entreprises WHERE id = ? AND org_id = ?');
$stmt->execute([$entrepriseId, $oid]);
if (!$stmt->fetch()) {
    jsonResponse(['error' => 'Entreprise introuvable'], 404);
}

// Handle photo upload
$photoFilename = $existing['photo']; // keep existing by default

if (!empty($_FILES['photo']['tmp_name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
    $config  = require basePath('config/app.php');
    $upload  = $config['upload'];
    $tmpFile = $_FILES['photo']['tmp_name'];
    $size    = $_FILES['photo']['size'];
    $mime    = mime_content_type($tmpFile);

    if ($size <= $upload['max_size'] && in_array($mime, $upload['allowed_types'])) {
        $extMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $ext    = $extMap[$mime] ?? 'jpg';
        $photoFilename = $id . '_' . time() . '.' . $ext;
        $destDir = basePath('storage/uploads/adherents');

        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }

        if (move_uploaded_file($tmpFile, $destDir . '/' . $photoFilename)) {
            // Delete old photo file if it exists
            if ($existing['photo']) {
                $oldPath = $destDir . '/' . $existing['photo'];
                if (is_file($oldPath)) {
                    @unlink($oldPath);
                }
            }
        } else {
            $photoFilename = $existing['photo']; // revert on failure
        }
    }
}

$stmt = db()->prepare('
    UPDATE adherents SET
        nom            = ?,
        prenom         = ?,
        entreprise_id  = ?,
        sexe           = ?,
        date_naissance = ?,
        telephone      = ?,
        categorie      = ?,
        plafond_annuel = ?,
        date_adhesion  = ?,
        statut         = ?,
        photo          = ?
    WHERE id = ? AND org_id = ?
');
$stmt->execute([
    $nom,
    $prenom,
    $entrepriseId,
    $sexe,
    $dateNaissance ?: null,
    $telephone ?: null,
    $categorie,
    $plafond,
    $dateAdhesion ?: null,
    $statut,
    $photoFilename,
    $id,
    $oid,
]);

jsonResponse(['ok' => true]);
