<?php

/**
 * GET /api/portail-adherent/remboursements
 * List adherent's own reimbursement claims.
 */

$au = requireAdherent();
$aid = (int)$au['id'];
$oid = adherentOrgId();

$stmt = db()->prepare("
    SELECT r.*,
        (SELECT COUNT(*) FROM remboursement_documents rd WHERE rd.remboursement_id = r.id) as nb_docs
    FROM remboursements r
    WHERE r.adherent_id = ? AND r.org_id = ?
    ORDER BY r.created_at DESC
    LIMIT 200
");
$stmt->execute([$aid, $oid]);

jsonResponse(['data' => $stmt->fetchAll()]);
