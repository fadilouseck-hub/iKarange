<?php

/**
 * GET  /api/applications-mobiles/feedback        Feedback sent from the apps (Profil › Donner mon avis).
 * POST /api/applications-mobiles/feedback/{id}   { status: new | read | done }
 */

require __DIR__ . '/_guard.php';

const FEEDBACK_STATUSES = ['new', 'read', 'done'];

if (requestMethod() === 'POST') {
    $status = (string)(jsonInput()['status'] ?? '');
    if (!in_array($status, FEEDBACK_STATUSES, true)) {
        jsonResponse(['error' => 'Statut invalide'], 422);
    }
    $stmt = db()->prepare('UPDATE mobile_feedback f JOIN adherents a ON a.id = f.adherent_id
        SET f.status = ? WHERE f.id = ? AND a.org_id = ?');
    $stmt->execute([$status, (int)($_GET['id'] ?? 0), $oid]);
    if ($stmt->rowCount() === 0) {
        $exists = db()->prepare('SELECT 1 FROM mobile_feedback f JOIN adherents a ON a.id = f.adherent_id WHERE f.id = ? AND a.org_id = ?');
        $exists->execute([(int)($_GET['id'] ?? 0), $oid]);
        if (!$exists->fetchColumn()) jsonResponse(['error' => 'Avis introuvable'], 404);
    }
    jsonResponse(['ok' => true]);
}

$stmt = db()->prepare("SELECT f.id, f.category, f.message, f.platform, f.app_version, f.os_version, f.device_model,
        f.locale, f.status, f.created_at, a.id AS adherent_id, a.nom, a.prenom, a.matricule
    FROM mobile_feedback f JOIN adherents a ON a.id = f.adherent_id
    WHERE a.org_id = ?
    ORDER BY f.status = 'new' DESC, f.created_at DESC
    LIMIT 500");
$stmt->execute([$oid]);
jsonResponse(['data' => $stmt->fetchAll()]);
