<?php

/**
 * POST /api/tiers-payant/facture-submit
 * Submit invoice batch with attached documents.
 * Creates one facture per enterprise (each gets a separate cheque).
 * Marks associated PECs as 'facturee'.
 *
 * Expects multipart/form-data with:
 *   - date_debut, date_fin (period)
 *   - entreprises[] (array of entreprise IDs to invoice, optional - default: all)
 *   - documents[] (uploaded files, optional)
 */

$presta = requirePrestataire();
$oid    = prestaOrgId();
$prestaId = (int)$presta['prestataire_id'];

if (!verifyCsrf()) {
    jsonResponse(['error' => 'Token CSRF invalide'], 403);
}

$dateDebut = trim($_POST['date_debut'] ?? '');
$dateFin   = trim($_POST['date_fin'] ?? '');
$selectedEnts = $_POST['entreprises'] ?? [];

if (!is_array($selectedEnts)) $selectedEnts = [];
$selectedEnts = array_map('intval', $selectedEnts);

// Validate uploaded documents
$maxSize = 5 * 1024 * 1024;
$allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
$uploadDir = basePath('storage/uploads/factures-prestataires');

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$files = $_FILES['documents'] ?? null;
$fileCount = ($files && is_array($files['name'])) ? count(array_filter($files['name'])) : 0;

// Validate each file before processing
for ($i = 0; $i < $fileCount; $i++) {
    if (empty($files['name'][$i])) continue;
    if ($files['error'][$i] !== UPLOAD_ERR_OK) {
        jsonResponse(['error' => 'Erreur upload: ' . $files['name'][$i]], 422);
    }
    if ($files['size'][$i] > $maxSize) {
        jsonResponse(['error' => 'Fichier trop volumineux (max 5 Mo): ' . $files['name'][$i]], 422);
    }
    $mime = mime_content_type($files['tmp_name'][$i]);
    if (!in_array($mime, $allowedTypes)) {
        jsonResponse(['error' => 'Type non autorise (JPEG, PNG, PDF): ' . $files['name'][$i]], 422);
    }
}

// Get PECs to invoice, grouped by enterprise
$where = [
    'pec.org_id = ?',
    'pec.prestataire_id = ?',
    "pec.statut IN ('approuvee')"  // Only approved PECs not yet invoiced
];
$params = [$oid, $prestaId];

if ($dateDebut) { $where[] = 'pec.date_soins >= ?'; $params[] = $dateDebut; }
if ($dateFin)   { $where[] = 'pec.date_soins <= ?'; $params[] = $dateFin; }

$whereStr = implode(' AND ', $where);

$stmt = db()->prepare("
    SELECT pec.id, pec.part_ipm, pec.montant_total,
           a.entreprise_id, e.raison_sociale as entreprise_nom
    FROM prises_en_charge pec
    LEFT JOIN adherents a ON a.id = pec.adherent_id
    LEFT JOIN entreprises e ON e.id = a.entreprise_id
    WHERE $whereStr
    ORDER BY a.entreprise_id
");
$stmt->execute($params);
$pecs = $stmt->fetchAll();

if (empty($pecs)) {
    jsonResponse(['error' => 'Aucune prestation approuvee a facturer pour cette periode'], 422);
}

// Group PECs by enterprise
$grouped = [];
foreach ($pecs as $p) {
    $eid = (int)($p['entreprise_id'] ?? 0);
    if (!isset($grouped[$eid])) {
        $grouped[$eid] = [
            'entreprise_id' => $eid,
            'entreprise_nom' => $p['entreprise_nom'] ?? 'Sans entreprise',
            'pec_ids' => [],
            'total_ipm' => 0,
        ];
    }
    $grouped[$eid]['pec_ids'][] = (int)$p['id'];
    $grouped[$eid]['total_ipm'] += (int)$p['part_ipm'];
}

// Filter by selected enterprises if specified
if (!empty($selectedEnts)) {
    $grouped = array_filter($grouped, fn($g) => in_array($g['entreprise_id'], $selectedEnts));
}

if (empty($grouped)) {
    jsonResponse(['error' => 'Aucune entreprise selectionnee'], 422);
}

$pdo = db();
$pdo->beginTransaction();

try {
    $createdFactures = [];
    $totalDocs = 0;

    foreach ($grouped as $group) {
        // Create facture
        $numero = generateNumero('FACT');
        $stmt = $pdo->prepare("
            INSERT INTO factures_prestataires
                (org_id, numero, prestataire_id, entreprise_id, montant_total, montant_paye,
                 date_facture, periode_debut, periode_fin, statut, observations)
            VALUES (?, ?, ?, ?, ?, 0, CURDATE(), ?, ?, 'en_attente', ?)
        ");
        $stmt->execute([
            $oid, $numero, $prestaId, $group['entreprise_id'] ?: null,
            $group['total_ipm'], $dateDebut ?: null, $dateFin ?: null,
            'Facture soumise via portail prestataire pour ' . $group['entreprise_nom']
        ]);
        $factureId = (int)$pdo->lastInsertId();

        // Mark PECs as facturee
        if (!empty($group['pec_ids'])) {
            $placeholders = implode(',', array_fill(0, count($group['pec_ids']), '?'));
            $stmt = $pdo->prepare("UPDATE prises_en_charge SET statut = 'facturee' WHERE id IN ($placeholders) AND org_id = ?");
            $stmt->execute([...$group['pec_ids'], $oid]);
        }

        // Save documents (attached to ALL factures in this batch)
        for ($i = 0; $i < $fileCount; $i++) {
            if (empty($files['name'][$i])) continue;

            $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
            if (!$ext) $ext = 'bin';
            $storedName = $factureId . '_' . time() . '_' . $i . '.' . $ext;
            $destPath = $uploadDir . '/' . $storedName;

            // Copy (not move) so each facture gets its own copy
            if (copy($files['tmp_name'][$i], $destPath)) {
                $stmt = $pdo->prepare("
                    INSERT INTO facture_prestataire_documents
                        (facture_id, nom_fichier, chemin, taille, type_mime)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $factureId,
                    $files['name'][$i],
                    'factures-prestataires/' . $storedName,
                    $files['size'][$i],
                    mime_content_type($destPath)
                ]);
                $totalDocs++;
            }
        }

        $createdFactures[] = [
            'id' => $factureId,
            'numero' => $numero,
            'entreprise' => $group['entreprise_nom'],
            'montant' => $group['total_ipm'],
            'nb_pec' => count($group['pec_ids']),
        ];
    }

    // Clean up temp files (since we used copy not move)
    for ($i = 0; $i < $fileCount; $i++) {
        if (!empty($files['tmp_name'][$i]) && is_file($files['tmp_name'][$i])) {
            @unlink($files['tmp_name'][$i]);
        }
    }

    $pdo->commit();

    jsonResponse([
        'ok' => true,
        'factures' => $createdFactures,
        'total_factures' => count($createdFactures),
        'total_documents' => $totalDocs,
    ], 201);

} catch (PDOException $e) {
    $pdo->rollBack();
    jsonResponse(['error' => 'Erreur creation: ' . $e->getMessage()], 500);
}
