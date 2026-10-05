<?php

/**
 * POST /api/remboursements/{id}
 * Update reimbursement claim status (admin).
 */

$user = requireAuth();
$oid = orgId();

if (!verifyCsrf()) {
    jsonResponse(['error' => 'Token CSRF invalide'], 403);
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    jsonResponse(['error' => 'ID requis'], 422);
}

// Verify ownership
$stmt = db()->prepare('SELECT id FROM remboursements WHERE id = ? AND org_id = ?');
$stmt->execute([$id, $oid]);
if (!$stmt->fetch()) {
    jsonResponse(['error' => 'Remboursement introuvable'], 404);
}

$input = jsonInput();
$statut           = $input['statut'] ?? null;
$montantRembourse = isset($input['montant_rembourse']) ? (int)$input['montant_rembourse'] : null;
$commentaire      = $input['commentaire_admin'] ?? null;

$sets = [];
$vals = [];

if ($statut) {
    $validStatuts = ['soumis', 'en_cours', 'valide', 'rejete', 'paye'];
    if (!in_array($statut, $validStatuts)) {
        jsonResponse(['error' => 'Statut invalide'], 422);
    }
    $sets[] = 'statut = ?';
    $vals[] = $statut;

    if ($statut === 'valide') {
        $sets[] = 'date_validation = CURDATE()';
    }
    if ($statut === 'paye') {
        $sets[] = 'date_paiement = CURDATE()';
    }
}

if ($montantRembourse !== null) {
    $sets[] = 'montant_rembourse = ?';
    $vals[] = $montantRembourse;
}

if ($commentaire !== null) {
    $sets[] = 'commentaire_admin = ?';
    $vals[] = $commentaire;
}

if (empty($sets)) {
    jsonResponse(['error' => 'Aucune modification'], 422);
}

$vals[] = $id;
$vals[] = $oid;

$stmt = db()->prepare("UPDATE remboursements SET " . implode(', ', $sets) . " WHERE id = ? AND org_id = ?");
$stmt->execute($vals);

jsonResponse(['ok' => true]);
