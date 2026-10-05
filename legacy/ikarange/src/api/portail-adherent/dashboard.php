<?php

/**
 * GET /api/portail-adherent/dashboard
 * Return adherent profile, enterprise info, summary stats.
 */

$au = requireAdherent();
$oid = adherentOrgId();
$aid = (int)$au['id'];

// Full adherent profile
$stmt = db()->prepare('
    SELECT a.*, e.raison_sociale as entreprise_nom, e.taux_prise_en_charge
    FROM adherents a
    LEFT JOIN entreprises e ON e.id = a.entreprise_id
    WHERE a.id = ? AND a.org_id = ?
');
$stmt->execute([$aid, $oid]);
$profile = $stmt->fetch();

// PEC stats
$stmt = db()->prepare("SELECT COUNT(*) FROM prises_en_charge WHERE adherent_id = ? AND org_id = ?");
$stmt->execute([$aid, $oid]);
$pecTotal = (int)$stmt->fetchColumn();

$stmt = db()->prepare("SELECT COUNT(*) FROM prises_en_charge WHERE adherent_id = ? AND org_id = ? AND statut = 'en_attente'");
$stmt->execute([$aid, $oid]);
$pecEnAttente = (int)$stmt->fetchColumn();

// Total consumed this year
$stmt = db()->prepare("
    SELECT COALESCE(SUM(part_ipm), 0) FROM prises_en_charge
    WHERE adherent_id = ? AND org_id = ? AND YEAR(date_soins) = YEAR(CURDATE())
    AND statut IN ('approuvee','facturee','reglee')
");
$stmt->execute([$aid, $oid]);
$consommeAnnee = (int)$stmt->fetchColumn();

// Remove sensitive fields
unset($profile['password_hash'], $profile['login']);

jsonResponse([
    'profile'         => $profile,
    'pec_total'       => $pecTotal,
    'pec_en_attente'  => $pecEnAttente,
    'consomme_annee'  => $consommeAnnee,
    'plafond_annuel'  => (int)($profile['plafond_annuel'] ?? 0),
]);
