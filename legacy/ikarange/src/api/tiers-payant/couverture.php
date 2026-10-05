<?php

/**
 * GET /api/tiers-payant/couverture/{id}
 * Get adherent coverage details: plafond annuel, consomme, disponible.
 */

$presta = requirePrestataire();
$oid    = prestaOrgId();
$id     = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    jsonResponse(['error' => 'ID invalide'], 400);
}

// Fetch adherent with entreprise
$stmt = db()->prepare('
    SELECT a.id, a.nom, a.prenom, a.matricule, a.sexe, a.categorie,
           a.date_naissance, a.telephone, a.plafond_annuel, a.statut,
           a.date_adhesion, a.photo,
           e.raison_sociale AS entreprise_nom
    FROM adherents a
    LEFT JOIN entreprises e ON e.id = a.entreprise_id
    WHERE a.id = ? AND a.org_id = ?
');
$stmt->execute([$id, $oid]);
$adherent = $stmt->fetch();

if (!$adherent) {
    jsonResponse(['error' => 'Adhérent introuvable'], 404);
}

// Calculate consumed amount this year (approved / billed / settled PECs)
$year = date('Y');
$stmt = db()->prepare('
    SELECT COALESCE(SUM(part_ipm), 0) as consomme
    FROM prises_en_charge
    WHERE adherent_id = ?
      AND org_id = ?
      AND statut IN (\'approuvee\', \'facturee\', \'reglee\')
      AND YEAR(date_soins) = ?
');
$stmt->execute([$id, $oid, $year]);
$row = $stmt->fetch();

$plafond   = (int)$adherent['plafond_annuel'];
$consomme  = (int)$row['consomme'];
$disponible = $plafond > 0 ? max(0, $plafond - $consomme) : null; // null = unlimited

// Default taux couverture (from org settings or fallback)
$tauxCouverture = 70;

// Get recent PECs for this adherent at this prestataire
$stmt = db()->prepare('
    SELECT pec.id, pec.numero, pec.type_acte, pec.date_soins,
           pec.montant_total, pec.part_ipm, pec.part_adherent,
           pec.taux_couverture, pec.statut, pec.motif, pec.created_at
    FROM prises_en_charge pec
    WHERE pec.adherent_id = ?
      AND pec.prestataire_id = ?
      AND pec.org_id = ?
    ORDER BY pec.created_at DESC
    LIMIT 20
');
$stmt->execute([$id, $presta['prestataire_id'], $oid]);
$historique = $stmt->fetchAll();

// Load bareme for this adherent's enterprise
$stmt = db()->prepare('
    SELECT b.type_acte, b.libelle, b.taux_couverture, b.plafond_acte,
           b.periode_plafond, b.plafond_par
    FROM baremes b
    JOIN adherents a ON a.entreprise_id = b.entreprise_id AND a.org_id = b.org_id
    WHERE a.id = ? AND a.org_id = ? AND b.is_active = 1
    ORDER BY b.id
');
$stmt->execute([$id, $oid]);
$baremes = $stmt->fetchAll();

// If bareme exists, use the first matching taux as default
if ($baremes) {
    $tauxCouverture = (float)$baremes[0]['taux_couverture'];
}

jsonResponse([
    'adherent'    => $adherent,
    'plafond'     => $plafond,
    'consomme'    => $consomme,
    'disponible'  => $disponible,
    'taux'        => $tauxCouverture,
    'historique'  => $historique,
    'baremes'     => $baremes,
]);
