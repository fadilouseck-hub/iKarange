<?php

/**
 * POST /api/primes
 * Create a new prime/cotisation.
 */

$user = requireAuth();

if (!verifyCsrf()) {
    jsonResponse(['error' => 'Token CSRF invalide'], 403);
}

$input = jsonInput();
$oid = orgId();

$entreprise_id    = (int)($input['entreprise_id'] ?? 0);
$mois             = trim($input['mois'] ?? '');
$montant          = (int)($input['montant'] ?? 0);
$nombre_adherents = (int)($input['nombre_adherents'] ?? 0);
$statut           = trim($input['statut'] ?? 'a_facturer');

// Validation
$errors = [];
if (!$entreprise_id) $errors[] = "L'entreprise est requise";
if (!$mois)          $errors[] = 'Le mois est requis';
if ($montant <= 0)   $errors[] = 'Le montant est requis';

if ($errors) {
    jsonResponse(['error' => implode(', ', $errors)], 422);
}

// Verify entreprise belongs to org
$stmt = db()->prepare('SELECT id FROM entreprises WHERE id = ? AND org_id = ?');
$stmt->execute([$entreprise_id, $oid]);
if (!$stmt->fetch()) {
    jsonResponse(['error' => 'Entreprise introuvable'], 404);
}

$stmt = db()->prepare('
    INSERT INTO primes
        (org_id, entreprise_id, mois, montant, nombre_adherents, statut)
    VALUES (?, ?, ?, ?, ?, ?)
');
$stmt->execute([
    $oid, $entreprise_id, $mois, $montant, $nombre_adherents, $statut,
]);

jsonResponse(['ok' => true, 'id' => (int)db()->lastInsertId()], 201);
