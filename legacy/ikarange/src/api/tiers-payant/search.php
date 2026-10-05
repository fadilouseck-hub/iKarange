<?php

/**
 * GET /api/tiers-payant/search?q=...
 * Search adherents by matricule or name for the prestataire portal.
 */

$presta = requirePrestataire();
$oid    = prestaOrgId();

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) {
    jsonResponse(['error' => 'Saisissez au moins 2 caractères'], 422);
}

$search = "%{$q}%";

$stmt = db()->prepare('
    SELECT a.id, a.nom, a.prenom, a.matricule, a.sexe, a.categorie,
           a.date_naissance, a.telephone, a.plafond_annuel, a.statut,
           a.date_adhesion, a.photo,
           e.raison_sociale AS entreprise_nom
    FROM adherents a
    LEFT JOIN entreprises e ON e.id = a.entreprise_id
    WHERE a.org_id = ?
      AND a.statut = \'actif\'
      AND (a.matricule LIKE ? OR a.nom LIKE ? OR a.prenom LIKE ?
           OR CONCAT(a.nom, \' \', a.prenom) LIKE ?)
    ORDER BY a.nom, a.prenom
    LIMIT 10
');
$stmt->execute([$oid, $search, $search, $search, $search]);
$results = $stmt->fetchAll();

jsonResponse(['data' => $results]);
