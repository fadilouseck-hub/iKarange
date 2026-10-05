<?php

/**
 * POST /api/adherents
 * Create a new adherent with auto-generated matricule.
 * Accepts multipart/form-data (for photo upload) or JSON.
 */

$user = requireAuth();
$oid  = orgId();

if (requestMethod() !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

if (!verifyCsrf()) {
    jsonResponse(['error' => 'Token CSRF invalide'], 403);
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
$stmt = db()->prepare('SELECT id, raison_sociale FROM entreprises WHERE id = ? AND org_id = ?');
$stmt->execute([$entrepriseId, $oid]);
$entreprise = $stmt->fetch();

if (!$entreprise) {
    jsonResponse(['error' => 'Entreprise introuvable'], 404);
}

// Auto-generate matricule
$prefix = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $entreprise['raison_sociale']), 0, 3));

$stmt = db()->prepare("
    SELECT matricule FROM adherents
    WHERE org_id = ? AND matricule LIKE ?
    ORDER BY matricule DESC
    LIMIT 1
");
$stmt->execute([$oid, $prefix . '-%']);
$lastMatricule = $stmt->fetchColumn();

if ($lastMatricule) {
    $lastNum = (int)substr($lastMatricule, strlen($prefix) + 1);
    $nextNum = $lastNum + 1;
} else {
    $nextNum = 1;
}

$matricule = $prefix . '-' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);

// Insert (without photo first — need the ID for filename)
$stmt = db()->prepare('
    INSERT INTO adherents (org_id, entreprise_id, nom, prenom, matricule, sexe, date_naissance, telephone, categorie, plafond_annuel, date_adhesion, statut)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
');
$stmt->execute([
    $oid,
    $entrepriseId,
    $nom,
    $prenom,
    $matricule,
    $sexe,
    $dateNaissance ?: null,
    $telephone ?: null,
    $categorie,
    $plafond,
    $dateAdhesion ?: null,
    $statut,
]);

$id = (int)db()->lastInsertId();

// Handle photo upload
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
            $stmt = db()->prepare('UPDATE adherents SET photo = ? WHERE id = ? AND org_id = ?');
            $stmt->execute([$photoFilename, $id, $oid]);
        }
    }
}

jsonResponse(['ok' => true, 'id' => $id, 'matricule' => $matricule], 201);
