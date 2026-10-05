<?php

/**
 * POST /api/baremes
 * Bulk save bareme rows for an entreprise.
 * Accepts: { entreprise_id, lignes: [{type_acte, libelle, taux_couverture, plafond_acte, periode_plafond, plafond_par}, ...] }
 */

$user = requireAuth();

if (!verifyCsrf()) {
    jsonResponse(['error' => 'Token CSRF invalide'], 403);
}

$oid   = orgId();
$input = jsonInput();

$entreprise_id = (int)($input['entreprise_id'] ?? 0);
$lignes        = $input['lignes'] ?? [];

if (!$entreprise_id) {
    jsonResponse(['error' => 'entreprise_id requis'], 400);
}

if (!is_array($lignes) || empty($lignes)) {
    jsonResponse(['error' => 'Aucune ligne fournie'], 400);
}

// Verify entreprise belongs to org
$stmt = db()->prepare('SELECT id FROM entreprises WHERE id = ? AND org_id = ?');
$stmt->execute([$entreprise_id, $oid]);
if (!$stmt->fetch()) {
    jsonResponse(['error' => 'Entreprise introuvable'], 404);
}

$pdo = db();
$pdo->beginTransaction();

try {
    // Delete existing rows
    $stmt = $pdo->prepare('DELETE FROM baremes WHERE org_id = ? AND entreprise_id = ?');
    $stmt->execute([$oid, $entreprise_id]);

    // Insert new rows
    $stmt = $pdo->prepare('
        INSERT INTO baremes (org_id, entreprise_id, type_acte, libelle, taux_couverture, plafond_acte, periode_plafond, plafond_par, is_active)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
    ');

    $count = 0;
    foreach ($lignes as $row) {
        $type_acte      = trim($row['type_acte'] ?? '');
        $libelle        = trim($row['libelle'] ?? '');
        $taux           = (float)($row['taux_couverture'] ?? 80);
        $plafond        = $row['plafond_acte'];
        $periode        = $row['periode_plafond'] ?? null;
        $plafond_par    = $row['plafond_par'] ?? 'beneficiaire';

        if (!$type_acte || !$libelle) continue;

        // Normalize: empty/zero plafond = NULL (frais reels)
        if ($plafond === '' || $plafond === null || $plafond === 0 || $plafond === '0') {
            $plafond = null;
            $periode = null;
        } else {
            $plafond = (int)$plafond;
            // Default period if plafond is set but period is empty
            if (!$periode || !in_array($periode, ['par_evenement', 'par_an', 'par_2_ans'])) {
                $periode = 'par_an';
            }
        }

        if (!in_array($plafond_par, ['beneficiaire', 'titulaire'])) {
            $plafond_par = 'beneficiaire';
        }

        $stmt->execute([
            $oid, $entreprise_id, $type_acte, $libelle,
            $taux, $plafond, $periode, $plafond_par
        ]);
        $count++;
    }

    $pdo->commit();
    jsonResponse(['ok' => true, 'count' => $count]);

} catch (\Exception $e) {
    $pdo->rollBack();
    jsonResponse(['error' => 'Erreur lors de la sauvegarde: ' . $e->getMessage()], 500);
}
