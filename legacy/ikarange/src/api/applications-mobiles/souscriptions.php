<?php

/**
 * GET /api/applications-mobiles/souscriptions
 * Contracts taken out in the apps (online subscription, test mode) with their payments.
 */

require __DIR__ . '/_guard.php';

// Product names come from the subscription catalogue; its labels use t() from the mobile API (French here).
if (!function_exists('t')) {
    function t(string $fr, string $en): string { return $fr; }
}
require_once basePath('src/api/mobile/subscription.php');

$policies = db()->prepare("SELECT p.id, p.number, p.product_id, p.coverage_rate, p.total_premium, p.status,
        p.start_date, p.end_date, p.created_at, a.id AS adherent_id, a.nom, a.prenom, a.matricule, a.telephone,
        JSON_LENGTH(p.snapshot, '$.members') AS members
    FROM mobile_policies p JOIN adherents a ON a.id = p.adherent_id
    WHERE a.org_id = ?
    ORDER BY p.created_at DESC
    LIMIT 500");
$policies->execute([$oid]);
$rows = $policies->fetchAll();

$payments = [];
if ($rows) {
    $ids = array_column($rows, 'id');
    $stmt = db()->prepare('SELECT policy_id, reference, amount, method, status, completed_at, created_at
        FROM mobile_payments WHERE policy_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY created_at DESC');
    $stmt->execute($ids);
    foreach ($stmt->fetchAll() as $payment) {
        $payments[$payment['policy_id']][] = $payment;
    }
}

foreach ($rows as &$row) {
    $row['product_name'] = subProduct($row['product_id'])['name'] ?? $row['product_id'];
    $row['payments'] = $payments[$row['id']] ?? [];
}
unset($row);

$testers = db()->prepare('SELECT COUNT(*) FROM mobile_online_subscribers s JOIN adherents a ON a.id = s.adherent_id WHERE a.org_id = ?');
$testers->execute([$oid]);

jsonResponse(['data' => $rows, 'online_members' => (int)$testers->fetchColumn(), 'test_mode' => true]);
