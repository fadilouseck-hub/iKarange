<?php

/**
 * GET /api/pec/{id}
 * Show a single prise en charge with joined names.
 */

$user = requireAuth();
$id  = (int)($_GET['id'] ?? 0);
$oid = orgId();

if (!$id) {
    jsonResponse(['error' => 'ID requis'], 400);
}

$stmt = db()->prepare("
    SELECT pec.*,
           a.nom AS adherent_nom, a.prenom AS adherent_prenom, a.matricule AS adherent_matricule,
           e.raison_sociale AS entreprise_nom,
           p.nom AS prestataire_nom
    FROM prises_en_charge pec
    LEFT JOIN adherents a ON a.id = pec.adherent_id
    LEFT JOIN entreprises e ON e.id = a.entreprise_id
    LEFT JOIN prestataires p ON p.id = pec.prestataire_id
    WHERE pec.id = ? AND pec.org_id = ?
");
$stmt->execute([$id, $oid]);
$pec = $stmt->fetch();

if (!$pec) {
    jsonResponse(['error' => 'Prise en charge introuvable'], 404);
}

jsonResponse($pec);
