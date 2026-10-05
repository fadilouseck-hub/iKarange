<?php

/**
 * POST /api/pec/{id}
 * Update an existing prise en charge.
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
$stmt = db()->prepare('SELECT * FROM prises_en_charge WHERE id = ? AND org_id = ?');
$stmt->execute([$id, $oid]);
$existing = $stmt->fetch();

if (!$existing) {
    jsonResponse(['error' => 'Prise en charge introuvable'], 404);
}

$input = jsonInput();

$adherent_id     = (int)($input['adherent_id'] ?? $existing['adherent_id']);
$prestataire_id  = (int)($input['prestataire_id'] ?? $existing['prestataire_id']);
$type_acte       = trim($input['type_acte'] ?? $existing['type_acte']);
$sous_type_acte  = trim($input['sous_type_acte'] ?? ($existing['sous_type_acte'] ?? ''));
$date_soins      = trim($input['date_soins'] ?? ($existing['date_soins'] ?? ''));
$montant_total   = (int)($input['montant_total'] ?? $existing['montant_total']);
$taux_couverture = (float)($input['taux_couverture'] ?? $existing['taux_couverture']);
$motif           = trim($input['motif'] ?? ($existing['motif'] ?? ''));
$statut          = trim($input['statut'] ?? $existing['statut']);
$observations    = trim($input['observations'] ?? ($existing['observations'] ?? ''));

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
        if ($part_ipm > $plafond_acte) {
            $part_ipm = $plafond_acte;
            $part_adherent = $montant_total - $part_ipm;
        }
    } else {
        $consomme = calculerConsommationActe($adherent_id, $oid, $baremeKey, $bareme, $date_soins);
        $disponible = max(0, $plafond_acte - $consomme);
        if ($part_ipm > $disponible) {
            $part_ipm = $disponible;
            $part_adherent = $montant_total - $part_ipm;
        }
    }
}

$stmt = db()->prepare('
    UPDATE prises_en_charge
    SET adherent_id = ?, prestataire_id = ?, type_acte = ?, sous_type_acte = ?, date_soins = ?,
        montant_total = ?, taux_couverture = ?, part_ipm = ?, part_adherent = ?,
        motif = ?, statut = ?, observations = ?
    WHERE id = ? AND org_id = ?
');
$stmt->execute([
    $adherent_id, $prestataire_id, $type_acte, $sous_type_acte ?: null, $date_soins ?: null,
    $montant_total, $taux_couverture, $part_ipm, $part_adherent,
    $motif, $statut, $observations,
    $id, $oid,
]);

jsonResponse(['ok' => true]);
