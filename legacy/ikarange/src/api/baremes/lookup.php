<?php

/**
 * GET /api/baremes/lookup?adherent_id={id}&type_acte={type}&sous_type_acte={sub}
 * Resolves bareme for an adherent's enterprise + act type.
 * Returns rate, ceiling info, consumed amount, and available amount.
 */

$user = requireAuth();
$oid  = orgId();

$adherent_id    = (int)($_GET['adherent_id'] ?? 0);
$type_acte      = trim($_GET['type_acte'] ?? '');
$sous_type_acte = trim($_GET['sous_type_acte'] ?? '');
$date_soins     = trim($_GET['date_soins'] ?? date('Y-m-d'));

if (!$adherent_id || !$type_acte) {
    jsonResponse(['error' => 'adherent_id et type_acte requis'], 400);
}

$bareme = lookupBareme($adherent_id, $oid, $type_acte, $sous_type_acte);

if (!$bareme) {
    // Fallback: get enterprise taux_prise_en_charge
    $stmt = db()->prepare('
        SELECT e.taux_prise_en_charge FROM entreprises e
        JOIN adherents a ON a.entreprise_id = e.id
        WHERE a.id = ? AND a.org_id = ?
    ');
    $stmt->execute([$adherent_id, $oid]);
    $ent = $stmt->fetch();

    jsonResponse([
        'has_bareme'       => false,
        'taux_couverture'  => (float)($ent['taux_prise_en_charge'] ?? 80),
        'plafond_acte'     => null,
        'periode_plafond'  => null,
        'plafond_par'      => null,
        'consomme_plafond' => 0,
        'disponible_plafond' => null,
    ]);
}

$key = $sous_type_acte ?: $type_acte;
$consomme = 0;
$disponible = null;

if ($bareme['plafond_acte'] !== null) {
    $plafond = (int)$bareme['plafond_acte'];

    if ($bareme['periode_plafond'] === 'par_evenement') {
        // Per-event: ceiling is per single PEC, show full amount as available
        $consomme = 0;
        $disponible = $plafond;
    } else {
        $consomme = calculerConsommationActe($adherent_id, $oid, $key, $bareme, $date_soins);
        $disponible = max(0, $plafond - $consomme);
    }
}

jsonResponse([
    'has_bareme'         => true,
    'taux_couverture'    => (float)$bareme['taux_couverture'],
    'plafond_acte'       => $bareme['plafond_acte'] !== null ? (int)$bareme['plafond_acte'] : null,
    'periode_plafond'    => $bareme['periode_plafond'],
    'plafond_par'        => $bareme['plafond_par'],
    'libelle'            => $bareme['libelle'],
    'consomme_plafond'   => $consomme,
    'disponible_plafond' => $disponible,
]);
