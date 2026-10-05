<?php

/**
 * POST /api/tiers-payant/pec
 * Create a prise en charge from the prestataire portal.
 */

$presta = requirePrestataire();
$oid    = prestaOrgId();

if (!verifyCsrf()) {
    jsonResponse(['error' => 'Token CSRF invalide'], 403);
}

$input = jsonInput();

$adherent_id     = (int)($input['adherent_id'] ?? 0);
$type_acte       = trim($input['type_acte'] ?? 'consultation');
$sous_type_acte  = trim($input['sous_type_acte'] ?? '');
$date_soins      = trim($input['date_soins'] ?? '');
$montant_total   = (int)($input['montant_total'] ?? 0);
$motif           = trim($input['motif'] ?? '');

// Validation
$errors = [];
if (!$adherent_id)   $errors[] = "L'adhérent est requis";
if (!$date_soins)    $errors[] = 'La date des soins est requise';
if ($montant_total <= 0) $errors[] = 'Le montant doit être supérieur à 0';

if ($errors) {
    jsonResponse(['error' => implode(', ', $errors)], 422);
}

// Validate type_acte
$validTypes = ['consultation','analyse','pharmacie','hospitalisation','imagerie',
               'dentaire','chirurgie','optique','maternite','autre'];
if (!in_array($type_acte, $validTypes)) {
    jsonResponse(['error' => 'Type d\'acte invalide'], 422);
}

// Verify adherent belongs to org and is active
$stmt = db()->prepare('SELECT id, plafond_annuel FROM adherents WHERE id = ? AND org_id = ? AND statut = \'actif\'');
$stmt->execute([$adherent_id, $oid]);
$adherent = $stmt->fetch();
if (!$adherent) {
    jsonResponse(['error' => 'Adhérent introuvable ou inactif'], 404);
}

// Bareme lookup
$bareme = lookupBareme($adherent_id, $oid, $type_acte, $sous_type_acte);
$taux_couverture = $bareme ? (float)$bareme['taux_couverture'] : 70;

// Check remaining plafond
$year = date('Y');
$stmt = db()->prepare('
    SELECT COALESCE(SUM(part_ipm), 0) as consomme
    FROM prises_en_charge
    WHERE adherent_id = ? AND org_id = ?
      AND statut IN (\'approuvee\', \'facturee\', \'reglee\')
      AND YEAR(date_soins) = ?
');
$stmt->execute([$adherent_id, $oid, $year]);
$consomme  = (int)$stmt->fetch()['consomme'];
$plafond   = (int)$adherent['plafond_annuel'];
$disponible = max(0, $plafond - $consomme);

$part_ipm      = (int)round($montant_total * $taux_couverture / 100);
$part_adherent = $montant_total - $part_ipm;

// Cap part_ipm to annual plafond
if ($part_ipm > $disponible && $plafond > 0) {
    $part_ipm      = $disponible;
    $part_adherent = $montant_total - $part_ipm;
}

// Enforce act-specific ceiling from bareme
if ($bareme && $bareme['plafond_acte'] !== null) {
    $plafond_acte = (int)$bareme['plafond_acte'];
    $baremeKey = $sous_type_acte ?: $type_acte;

    if ($bareme['periode_plafond'] === 'par_evenement') {
        if ($part_ipm > $plafond_acte) {
            $part_ipm = $plafond_acte;
            $part_adherent = $montant_total - $part_ipm;
        }
    } else {
        $consomme_acte = calculerConsommationActe($adherent_id, $oid, $baremeKey, $bareme, $date_soins);
        $disponible_acte = max(0, $plafond_acte - $consomme_acte);
        if ($part_ipm > $disponible_acte) {
            $part_ipm = $disponible_acte;
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
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'en_attente\', ?)
');
$stmt->execute([
    $oid, $numero, $adherent_id, $presta['prestataire_id'],
    $type_acte, $sous_type_acte ?: null, $date_soins, $montant_total, $taux_couverture,
    $part_ipm, $part_adherent, $motif,
    'Cree via portail prestataire par ' . ($presta['nom_complet'] ?? $presta['login'])
]);

jsonResponse([
    'ok'      => true,
    'id'      => (int)db()->lastInsertId(),
    'numero'  => $numero,
    'message' => 'Prise en charge créée avec succès'
], 201);
