<?php

/**
 * POST /api/portail-adherent/remboursement-create
 * Submit a new reimbursement claim with file uploads.
 * Expects multipart/form-data with fields + files[].
 */

$au = requireAdherent();

if (!verifyCsrf()) {
    jsonResponse(['error' => 'Token CSRF invalide'], 403);
}

$aid = (int)$au['id'];
$oid = adherentOrgId();

$typeActe  = trim($_POST['type_acte'] ?? '');
$dateSoins = trim($_POST['date_soins'] ?? '');
$montant   = (int)($_POST['montant'] ?? 0);
$motif     = trim($_POST['motif'] ?? '');

if (!$typeActe || !$dateSoins || $montant <= 0) {
    jsonResponse(['error' => 'Type d\'acte, date des soins et montant sont requis'], 422);
}

// Validate uploaded files
$maxSize = 5 * 1024 * 1024; // 5MB
$allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
$uploadDir = basePath('storage/uploads/remboursements');

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$files = $_FILES['documents'] ?? null;
$fileCount = 0;
if ($files && is_array($files['name'])) {
    $fileCount = count(array_filter($files['name']));
}

if ($fileCount === 0) {
    jsonResponse(['error' => 'Au moins un justificatif est requis (facture, ordonnance, etc.)'], 422);
}

if ($fileCount > 10) {
    jsonResponse(['error' => 'Maximum 10 fichiers par demande'], 422);
}

// Validate each file before processing
for ($i = 0; $i < $fileCount; $i++) {
    if ($files['error'][$i] !== UPLOAD_ERR_OK) {
        jsonResponse(['error' => 'Erreur upload fichier: ' . $files['name'][$i]], 422);
    }
    if ($files['size'][$i] > $maxSize) {
        jsonResponse(['error' => 'Fichier trop volumineux (max 5 Mo): ' . $files['name'][$i]], 422);
    }
    $mime = mime_content_type($files['tmp_name'][$i]);
    if (!in_array($mime, $allowedTypes)) {
        jsonResponse(['error' => 'Type non autorise (JPEG, PNG, PDF uniquement): ' . $files['name'][$i]], 422);
    }
}

$pdo = db();
$pdo->beginTransaction();

try {
    // Create remboursement
    $numero = generateNumero('RMB');
    $stmt = $pdo->prepare("
        INSERT INTO remboursements (org_id, adherent_id, numero, type_acte, date_soins, montant, motif, statut)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'soumis')
    ");
    $stmt->execute([$oid, $aid, $numero, $typeActe, $dateSoins, $montant, $motif]);
    $rembId = (int)$pdo->lastInsertId();

    // Save documents
    $savedDocs = 0;
    for ($i = 0; $i < $fileCount; $i++) {
        if (empty($files['name'][$i])) continue;

        $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
        if (!$ext) $ext = 'bin';
        $storedName = $rembId . '_' . time() . '_' . $i . '.' . $ext;
        $destPath = $uploadDir . '/' . $storedName;

        if (move_uploaded_file($files['tmp_name'][$i], $destPath)) {
            $stmt = $pdo->prepare("
                INSERT INTO remboursement_documents (remboursement_id, nom_fichier, chemin, taille, type_mime)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $rembId,
                $files['name'][$i],
                'remboursements/' . $storedName,
                $files['size'][$i],
                mime_content_type($destPath)
            ]);
            $savedDocs++;
        }
    }

    $pdo->commit();

    jsonResponse([
        'ok'     => true,
        'id'     => $rembId,
        'numero' => $numero,
        'docs'   => $savedDocs,
    ], 201);

} catch (PDOException $e) {
    $pdo->rollBack();
    jsonResponse(['error' => 'Erreur creation: ' . $e->getMessage()], 500);
}
