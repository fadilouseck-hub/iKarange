<?php

/**
 * GET /api/tiers-payant/facture-doc/{id}
 * Serve a facture document. Auth: prestataire (owner) or admin (same org).
 */

$presta = prestaUser();
$admin = authUser();

if (!$presta && !$admin) {
    http_response_code(401);
    echo 'Non autorise';
    exit;
}

$docId = (int)($_GET['id'] ?? 0);
if (!$docId) {
    http_response_code(400);
    echo 'ID requis';
    exit;
}

$stmt = db()->prepare("
    SELECT fpd.*, f.org_id, f.prestataire_id
    FROM facture_prestataire_documents fpd
    JOIN factures_prestataires f ON f.id = fpd.facture_id
    WHERE fpd.id = ?
");
$stmt->execute([$docId]);
$doc = $stmt->fetch();

if (!$doc) {
    http_response_code(404);
    echo 'Document introuvable';
    exit;
}

// Access check
if ($presta && (int)$doc['prestataire_id'] !== (int)$presta['prestataire_id']) {
    http_response_code(403);
    echo 'Acces refuse';
    exit;
}
if ($admin && (int)$doc['org_id'] !== orgId()) {
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
