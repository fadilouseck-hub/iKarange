<?php

/**
 * POST /api/primes/{id}
 * Update an existing prime/cotisation.
 */

$user = requireAuth();

if (!verifyCsrf()) {
    jsonResponse(['error' => 'Token CSRF invalide'], 403);
}

$id  = (int)($_GET['id'] ?? 0);
$oid = orgId();

if (!$id) {
    jsonResponse(['error' => 'ID requis'], 400);
}

// Verify record exists and belongs to org
$stmt = db()->prepare('SELECT * FROM primes WHERE id = ? AND org_id = ?');
$stmt->execute([$id, $oid]);
$existing = $stmt->fetch();

if (!$existing) {
    jsonResponse(['error' => 'Cotisation introuvable'], 404);
}

$input = jsonInput();

$entreprise_id    = (int)($input['entreprise_id'] ?? $existing['entreprise_id']);
$mois             = trim($input['mois'] ?? $existing['mois']);
$montant          = (int)($input['montant'] ?? $existing['montant']);
$nombre_adherents = (int)($input['nombre_adherents'] ?? $existing['nombre_adherents']);
$statut           = trim($input['statut'] ?? $existing['statut']);
$date_paiement    = trim($input['date_paiement'] ?? ($existing['date_paiement'] ?? ''));

$stmt = db()->prepare('
    UPDATE primes
    SET entreprise_id = ?, mois = ?, montant = ?, nombre_adherents = ?,
        statut = ?, date_paiement = ?
    WHERE id = ? AND org_id = ?
');
$stmt->execute([
    $entreprise_id, $mois, $montant, $nombre_adherents,
    $statut, $date_paiement ?: null,
    $id, $oid,
]);

jsonResponse(['ok' => true]);
