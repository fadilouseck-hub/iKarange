<?php

/**
 * GET /api/adherents/{id}/wallet-pass
 * Generate and download an Apple Wallet .pkpass for the adherent.
 */

$user = requireAuth();
$oid  = orgId();
$id   = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    jsonResponse(['error' => 'ID invalide'], 400);
}

// Fetch adherent with entreprise name (same as show.php)
$stmt = db()->prepare('
    SELECT a.*, e.raison_sociale AS entreprise_nom
    FROM adherents a
    LEFT JOIN entreprises e ON e.id = a.entreprise_id
    WHERE a.id = ? AND a.org_id = ?
');
$stmt->execute([$id, $oid]);
$adherent = $stmt->fetch();

if (!$adherent) {
    jsonResponse(['error' => 'Adhérent introuvable'], 404);
}

// Generate .pkpass
require_once basePath('src/core/AppleWallet.php');

try {
    $wallet = new AppleWallet();
    $pkpass = $wallet->generate($adherent);
} catch (RuntimeException $e) {
    $debug = filter_var(getenv('APP_DEBUG') ?: false, FILTER_VALIDATE_BOOLEAN);
    $msg   = $debug
        ? $e->getMessage()
        : 'Erreur lors de la génération du pass Apple Wallet';
    jsonResponse(['error' => $msg], 500);
}

// Serve the .pkpass download
$safeMatricule = preg_replace('/[^a-zA-Z0-9_-]/', '', $adherent['matricule'] ?? 'carte');
$filename = 'ikarange-' . $safeMatricule . '.pkpass';

header('Content-Type: application/vnd.apple.pkpass');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($pkpass));
header('Cache-Control: no-store, no-cache');
echo $pkpass;
exit;
