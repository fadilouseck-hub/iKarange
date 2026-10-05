<?php

/**
 * GET /api/portail-adherent/baremes
 * Return enterprise baremes with consumption data for the adherent.
 */

$au = requireAdherent();
$oid = adherentOrgId();
$aid = (int)$au['id'];
$eid = (int)$au['entreprise_id'];

// Get enterprise baremes
$stmt = db()->prepare('SELECT * FROM baremes WHERE entreprise_id = ? AND org_id = ? AND is_active = 1 ORDER BY type_acte');
$stmt->execute([$eid, $oid]);
$baremes = $stmt->fetchAll();

// Add consumption data for each
foreach ($baremes as &$b) {
    $b['consomme'] = calculerConsommationActe($aid, $oid, $b['type_acte'], $b);
    $b['disponible'] = $b['plafond_acte'] ? max(0, (int)$b['plafond_acte'] - $b['consomme']) : null;
}

jsonResponse(['data' => $baremes]);
