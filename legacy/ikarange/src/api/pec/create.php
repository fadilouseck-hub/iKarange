<?php

/**
 * POST /api/pec
 * Create a new prise en charge.
 */

$user = requireAuth();

if (!verifyCsrf()) {
    jsonResponse(['error' => 'Token CSRF invalide'], 403);
}

$input = jsonInput();
$oid = orgId();

$adherent_id     = (int)($input['adherent_id'] ?? 0);
$prestataire_id  = (int)($input['prestataire_id'] ?? 0);
$type_acte       = trim($input['type_acte'] ?? 'consultation');
$sous_type_acte  = trim($input['sous_type_acte'] ?? '');
$date_soins      = trim($input['date_soins'] ?? '');
$montant_total   = (int)($input['montant_total'] ?? 0);
$taux_couverture = (float)($input['taux_couverture'] ?? 80);
$motif           = trim($input['motif'] ?? '');
$statut          = trim($input['statut'] ?? 'en_attente');
$observations    = trim($input['observations'] ?? '');

// Validation
$errors = [];
if (!$adherent_id)    $errors[] = "L'adhérent est requis";
if (!$prestataire_id) $errors[] = 'Le prestataire est requis';

if ($errors) {
    jsonResponse(['error' => implode(', ', $errors)], 422);
}

// Verify adherent belongs to org
$stmt = db()->prepare('SELECT id FROM adherents WHERE id = ? AND org_id = ?');
$stmt->execute([$adherent_id, $oid]);
if (!$stmt->fetch()) {
    jsonResponse(['error' => 'Adhérent introuvable'], 404);
}

// Verify prestataire belongs to org
$stmt = db()->prepare('SELECT id FROM prestataires WHERE id = ? AND org_id = ?');
$stmt->execute([$prestataire_id, $oid]);
if (!$stmt->fetch()) {
    jsonResponse(['error' => 'Prestataire introuvable'], 404);
}

// Bareme lookup — override taux and enforce ceilings
$bareme = lookupBareme($adherent_id, $oid, $type_acte, $sous_type_acte);
if ($bareme) {
    $taux_couverture = (float)$bareme['taux_couverture'];
}

// Calculate parts
$part_ipm = (int)round($montant_total * $taux_couverture / 100);
$part_adherent = $montant_total - $part_ipm;

// Enforce act-specific ceiling
if ($bareme && $bareme['plafond_acte'] !== null) {
    $plafond_acte = (int)$bareme['plafond_acte'];
    $baremeKey = $sous_type_acte ?: $type_acte;

    if ($bareme['periode_plafond'] === 'par_evenement') {
        // Per-event: cap this single PEC
        if ($part_ipm > $plafond_acte) {
            $part_ipm = $plafond_acte;
            $part_adherent = $montant_total - $part_ipm;
        }
    } else {
        // Cumulative: check consumed amount
        $consomme = calculerConsommationActe($adherent_id, $oid, $baremeKey, $bareme, $date_soins);
        $disponible = max(0, $plafond_acte - $consomme);
        if ($part_ipm > $disponible) {
            $part_ipm = $disponible;
            $part_adherent = $montant_total - $part_ipm;
        }
    }
}

// Generate numero
$numero = generateNumero('PEC');

$stmt = db()->prepare('
    INSERT INTO prises_en_charge
        (org_id, numero, adherent_id, prestataire_id, type_acte, sous_type_acte, date_soins,
         montant_total, taux_couverture, part_ipm, part_adherent, motif, statut, observations)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
');
$stmt->execute([
    $oid, $numero, $adherent_id, $prestataire_id, $type_acte,
    $sous_type_acte ?: null, $date_soins ?: null, $montant_total, $taux_couverture,
    $part_ipm, $part_adherent, $motif, $statut, $observations,
]);

jsonResponse(['ok' => true, 'id' => (int)db()->lastInsertId(), 'numero' => $numero], 201);
