<?php

/**
 * GET /api/assureurs
 * List all assureurs (organizations) with stats. Super-admin only.
 */

requireSuperAdmin();

$stmt = db()->prepare('
    SELECT o.*,
        (SELECT COUNT(*) FROM entreprises e WHERE e.org_id = o.id) as nb_entreprises,
        (SELECT COUNT(*) FROM adherents a WHERE a.org_id = o.id) as nb_adherents,
        (SELECT COUNT(*) FROM prestataires p WHERE p.org_id = o.id) as nb_prestataires,
        (SELECT COUNT(*) FROM prises_en_charge pc WHERE pc.org_id = o.id) as nb_pec
    FROM organizations o
    ORDER BY o.name ASC
');
$stmt->execute();

jsonResponse(['data' => $stmt->fetchAll()]);
