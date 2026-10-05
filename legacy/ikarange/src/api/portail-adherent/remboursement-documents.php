<?php

/**
 * GET /api/portail-adherent/remboursement-doc/{id}
 * Serve a reimbursement document file. Auth check: must own the claim.
 */

$au = adherentUser();
$adminUser = authUser();

if (!$au && !$adminUser) {
    jsonResponse(['error' => 'Non autorise'], 401);
}

$docId = (int)($_GET['id'] ?? 0);
if (!$docId) {
    jsonResponse(['error' => 'ID requis'], 422);
}

// Fetch document with ownership check
$stmt = db()->prepare("
    SELECT rd.*, r.adherent_id, r.org_id
    FROM remboursement_documents rd
    JOIN remboursements r ON r.id = rd.remboursement_id
    WHERE rd.id = ?
");
$stmt->execute([$docId]);
$doc = $stmt->fetch();

if (!$doc) {
    http_response_code(404);
    echo 'Document introuvable';
    exit;
}

// Access check: adherent can only see own docs, admin can see org docs
if ($au && (int)$doc['adherent_id'] !== (int)$au['id']) {
    http_response_code(403);
    echo 'Acces refuse';
    exit;
}
if ($adminUser && (int)$doc['org_id'] !== orgId()) {
    http_response_code(403);
    echo 'Acces refuse';
    exit;
}

$filePath = basePath('storage/uploads/' . $doc['chemin']);
if (!is_file($filePath)) {
    http_response_code(404);
    echo 'Fichier introuvable';
    exit;
}

$mime = $doc['type_mime'] ?: 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . $doc['nom_fichier'] . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: private, max-age=3600');
readfile($filePath);
exit;
