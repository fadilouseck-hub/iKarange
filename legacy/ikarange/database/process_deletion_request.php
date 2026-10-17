<?php
/**
 * Processes account-deletion requests made in the mobile apps (Profil › Supprimer mon compte) or on the public
 * page /{tenant}/suppression-compte. Run by the account manager on the server; dry run unless --confirm.
 *
 * What it does for the member (matches the public page "Ce qui est supprimé / Ce qui est conservé"):
 *  - deletes app-only data: sessions and QR tokens, devices and preferences, claim drafts, unfinished uploads
 *    (files too), medical-records documents (encrypted files too), feedback messages, assistant usage, quotes;
 *  - removes app access: the member's password is cleared (app and web portal sign-in stop working);
 *  - keeps the insurance records the insurer must retain (member identity, contract, claims, payments,
 *    approvals) — they follow the legal retention period, outside this script;
 *  - marks the request "processed".
 *
 * Usage (from the site root):
 *   php database/process_deletion_request.php --list
 *   php database/process_deletion_request.php --id=12            (dry run: shows what would be deleted)
 *   php database/process_deletion_request.php --id=12 --confirm
 *   php database/process_deletion_request.php --id=12 --reject   (e.g. identity doubt; nothing is deleted)
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
$envFile = $root . '/.env';
if (!is_file($envFile)) {
    fwrite(STDERR, "No .env found in $root\n");
    exit(1);
}
foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#') continue;
    [$k, $v] = array_pad(explode('=', $line, 2), 2, '');
    // Variables already set in the environment win (e.g. a local database copy, as in dev/serve.sh).
    if (getenv(trim($k)) === false) putenv(trim($k) . '=' . trim($v));
}
$pdo = new PDO(
    'mysql:host=' . (getenv('DB_HOST') ?: '127.0.0.1') . ';port=' . (getenv('DB_PORT') ?: '3306') . ';dbname=' . (getenv('DB_NAME') ?: 'ikarange') . ';charset=utf8mb4',
    getenv('DB_USER') ?: 'root',
    getenv('DB_PASS') ?: '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

$options = getopt('', ['list', 'id:', 'confirm', 'reject']);

if (isset($options['list'])) {
    $rows = $pdo->query("SELECT r.id, r.adherent_id, r.status, r.created_at, r.reason, a.login, a.prenom, a.nom
        FROM mobile_deletion_requests r LEFT JOIN adherents a ON a.id = r.adherent_id ORDER BY r.status = 'requested' DESC, r.created_at")->fetchAll();
    if (!$rows) exit("No deletion requests.\n");
    foreach ($rows as $r) {
        printf("#%d  %-9s  %s  member %d (%s %s, login %s)  %s\n", $r['id'], $r['status'], $r['created_at'], $r['adherent_id'],
            $r['prenom'] ?? '?', $r['nom'] ?? '?', $r['login'] ?? '-', $r['reason'] !== '' ? '— ' . mb_substr((string)$r['reason'], 0, 80) : '');
    }
    exit(0);
}

$id = (int)($options['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM mobile_deletion_requests WHERE id = ?');
$stmt->execute([$id]);
$request = $stmt->fetch();
if (!$request) {
    fwrite(STDERR, "Request #$id not found. Use --list.\n");
    exit(1);
}
if ($request['status'] !== 'requested') {
    fwrite(STDERR, "Request #$id is already {$request['status']}.\n");
    exit(1);
}
$member = (int)$request['adherent_id'];

if (isset($options['reject'])) {
    $pdo->prepare("UPDATE mobile_deletion_requests SET status = 'rejected' WHERE id = ?")->execute([$id]);
    exit("Request #$id rejected. Nothing was deleted.\n");
}

// App-only tables deleted for the member (insurance records are not listed here on purpose).
$tables = ['mobile_tokens', 'mobile_qr_tokens', 'mobile_devices', 'mobile_preferences', 'mobile_claim_drafts',
    'mobile_feedback', 'mobile_assistant_usage', 'mobile_quotes', 'mobile_uploads', 'mobile_vault_documents'];
$counts = [];
foreach ($tables as $table) {
    $count = $pdo->prepare("SELECT COUNT(*) FROM `$table` WHERE adherent_id = ?");
    $count->execute([$member]);
    $counts[$table] = (int)$count->fetchColumn();
}
$vaultFiles = $pdo->prepare('SELECT path FROM mobile_vault_documents WHERE adherent_id = ?');
$vaultFiles->execute([$member]);
$files = array_map(fn($p) => $root . '/storage/uploads/' . $p, $vaultFiles->fetchAll(PDO::FETCH_COLUMN));
$uploads = $pdo->prepare('SELECT id FROM mobile_uploads WHERE adherent_id = ?');
$uploads->execute([$member]);
foreach ($uploads->fetchAll(PDO::FETCH_COLUMN) as $uploadId) {
    $files[] = $root . '/storage/uploads/mobile-tmp/' . preg_replace('/[^a-f0-9]/', '', $uploadId) . '.part';
}

echo "Request #$id — member $member\n";
foreach ($counts as $table => $n) printf("  %-24s %d row(s)\n", $table, $n);
printf("  %-24s %d file(s)\n", 'files', count(array_filter($files, 'is_file')));
echo "  app access               password cleared\n";
echo "  kept                     member identity, contract, claims, payments, approvals (legal retention)\n";

if (!isset($options['confirm'])) {
    exit("\nDry run. Re-run with --confirm to delete.\n");
}

$pdo->beginTransaction();
try {
    foreach ($tables as $table) {
        $pdo->prepare("DELETE FROM `$table` WHERE adherent_id = ?")->execute([$member]);
    }
    $pdo->prepare('UPDATE adherents SET password_hash = NULL WHERE id = ?')->execute([$member]);
    $pdo->prepare("UPDATE mobile_deletion_requests SET status = 'processed' WHERE id = ?")->execute([$id]);
    $pdo->commit();
} catch (Throwable $error) {
    $pdo->rollBack();
    fwrite(STDERR, "Failed, nothing changed: " . $error->getMessage() . "\n");
    exit(1);
}
// Files after the commit: a failed unlink leaves an orphan encrypted file, never a dangling database row.
$removed = 0;
foreach ($files as $file) {
    if (is_file($file) && @unlink($file)) $removed++;
}
echo "\nDone: request #$id processed, $removed file(s) removed.\n";
