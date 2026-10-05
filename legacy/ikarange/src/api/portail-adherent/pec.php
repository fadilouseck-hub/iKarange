<?php

/**
 * GET /api/portail-adherent/pec
 * Return adherent's PEC history.
 */

$au = requireAdherent();
$oid = adherentOrgId();
$aid = (int)$au['id'];

$stmt = db()->prepare('
    SELECT p.id, p.numero, p.type_acte, p.sous_type_acte, p.date_soins,
           p.montant_total, p.part_ipm, p.part_adherent, p.taux_couverture,
           p.statut, p.motif, p.observations, p.created_at,
           pr.nom as prestataire_nom
    FROM prises_en_charge p
    LEFT JOIN prestataires pr ON pr.id = p.prestataire_id
    WHERE p.adherent_id = ? AND p.org_id = ?
    ORDER BY p.date_soins DESC
    LIMIT 100
');
$stmt->execute([$aid, $oid]);

jsonResponse(['data' => $stmt->fetchAll()]);
