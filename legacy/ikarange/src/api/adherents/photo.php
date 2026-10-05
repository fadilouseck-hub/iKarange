<?php

/**
 * GET /api/adherents/photo/{id}
 * Serve an adherent's photo from storage.
 */

$user = requireAuth();
$oid  = orgId();
$id   = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    http_response_code(404);
    exit;
}

$stmt = db()->prepare('SELECT photo FROM adherents WHERE id = ? AND org_id = ?');
$stmt->execute([$id, $oid]);
$photo = $stmt->fetchColumn();

if (!$photo) {
    http_response_code(404);
    exit;
}

$path = basePath('storage/uploads/adherents/' . $photo);

if (!is_file($path)) {
    http_response_code(404);
    exit;
}

$ext = strtolower(pathinfo($photo, PATHINFO_EXTENSION));
$mimeMap = [
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'webp' => 'image/webp',
];

$mime = $mimeMap[$ext] ?? 'application/octet-stream';

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Cache-Control: public, max-age=86400');
readfile($path);
exit;
