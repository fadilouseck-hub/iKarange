<?php

/**
 * GET  /api/applications-mobiles/suppressions        Account-deletion requests from the apps and the public page.
 * GET  /api/applications-mobiles/suppressions/{id}   What processing would delete (preview).
 * POST /api/applications-mobiles/suppressions/{id}   { action: process | reject }
 */

require __DIR__ . '/_guard.php';
require_once basePath('src/core/AccountDeletion.php');

$deletion = new AccountDeletion(db(), basePath());
$id = (int)($_GET['id'] ?? 0);

if ($id) {
    $request = $deletion->find($id, $oid);
    if (!$request) {
        jsonResponse(['error' => 'Demande introuvable'], 404);
    }
    if (requestMethod() === 'GET') {
        jsonResponse(['data' => $request + ['preview' => $deletion->preview((int)$request['adherent_id'])]]);
    }
    if ($request['status'] !== 'requested') {
        jsonResponse(['error' => 'Cette demande a déjà été traitée.'], 409);
    }
    $action = (string)(jsonInput()['action'] ?? '');
    try {
        if ($action === 'process') {
            $removed = $deletion->process($id, (int)$request['adherent_id']);
            jsonResponse(['ok' => true, 'files_removed' => $removed]);
        }
        if ($action === 'reject') {
            $deletion->reject($id);
            jsonResponse(['ok' => true]);
        }
    } catch (Throwable $error) {
        jsonResponse(['error' => 'Échec, rien n\'a été modifié.'], 500);
    }
    jsonResponse(['error' => 'Action invalide'], 422);
}

$stmt = db()->prepare("SELECT r.id, r.status, r.reason, r.created_at, a.id AS adherent_id, a.nom, a.prenom, a.matricule,
        a.telephone, (a.password_hash IS NOT NULL) AS has_access
    FROM mobile_deletion_requests r JOIN adherents a ON a.id = r.adherent_id
    WHERE a.org_id = ?
    ORDER BY r.status = 'requested' DESC, r.created_at DESC
    LIMIT 500");
$stmt->execute([$oid]);
jsonResponse(['data' => $stmt->fetchAll()]);
