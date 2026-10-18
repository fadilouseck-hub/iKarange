<?php

/**
 * Account-deletion requests made in the mobile apps (Profil › Supprimer mon compte) or on the public page
 * /{tenant}/suppression-compte. Shared by the back-office (Applications mobiles) and database/process_deletion_request.php.
 *
 * Processing a request (matches the public page "Ce qui est supprimé / Ce qui est conservé"):
 *  - deletes app-only data: sessions and QR tokens, devices and preferences, claim drafts, unfinished uploads
 *    (files too), medical-records documents (encrypted files too), feedback messages, assistant usage, quotes;
 *  - removes app access: the member's password is cleared (app and web portal sign-in stop working);
 *  - keeps the insurance records the insurer must retain (member identity, contract, claims, payments,
 *    approvals) — they follow the legal retention period;
 *  - marks the request "processed".
 */
final class AccountDeletion
{
    /** App-only tables emptied for the member (insurance records are not listed here on purpose). */
    public const TABLES = ['mobile_tokens', 'mobile_qr_tokens', 'mobile_devices', 'mobile_preferences', 'mobile_claim_drafts',
        'mobile_feedback', 'mobile_assistant_usage', 'mobile_quotes', 'mobile_uploads', 'mobile_vault_documents'];

    public function __construct(private PDO $pdo, private string $root) {}

    /** The request, or null when it does not exist (or belongs to another organization when $orgId is given). */
    public function find(int $id, ?int $orgId = null): ?array
    {
        $sql = 'SELECT r.*, a.org_id FROM mobile_deletion_requests r JOIN adherents a ON a.id = r.adherent_id WHERE r.id = ?';
        $params = [$id];
        if ($orgId !== null) {
            $sql .= ' AND a.org_id = ?';
            $params[] = $orgId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** What processing would delete: row count per table and the number of files present on disk. */
    public function preview(int $memberId): array
    {
        $counts = [];
        foreach (self::TABLES as $table) {
            $count = $this->pdo->prepare("SELECT COUNT(*) FROM `$table` WHERE adherent_id = ?");
            $count->execute([$memberId]);
            $counts[$table] = (int)$count->fetchColumn();
        }
        return ['tables' => $counts, 'files' => count(array_filter($this->files($memberId), 'is_file'))];
    }

    /** Deletes the app data and clears the password in one transaction, then removes the files. Returns files removed. */
    public function process(int $requestId, int $memberId): int
    {
        $files = $this->files($memberId);
        $this->pdo->beginTransaction();
        try {
            foreach (self::TABLES as $table) {
                $this->pdo->prepare("DELETE FROM `$table` WHERE adherent_id = ?")->execute([$memberId]);
            }
            $this->pdo->prepare('UPDATE adherents SET password_hash = NULL WHERE id = ?')->execute([$memberId]);
            $this->markStatus($requestId, 'processed');
            $this->pdo->commit();
        } catch (Throwable $error) {
            $this->pdo->rollBack();
            throw $error;
        }
        // Files after the commit: a failed unlink leaves an orphan encrypted file, never a dangling database row.
        $removed = 0;
        foreach ($files as $file) {
            if (is_file($file) && @unlink($file)) $removed++;
        }
        return $removed;
    }

    /** Rejects the request (e.g. identity doubt); nothing is deleted. */
    public function reject(int $requestId): void
    {
        $this->markStatus($requestId, 'rejected');
    }

    private function markStatus(int $requestId, string $status): void
    {
        $stmt = $this->pdo->prepare("UPDATE mobile_deletion_requests SET status = ? WHERE id = ? AND status = 'requested'");
        $stmt->execute([$status, $requestId]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException("Request #$requestId is no longer pending.");
        }
    }

    /** Medical-records files and unfinished uploads stored for the member. */
    private function files(int $memberId): array
    {
        $vault = $this->pdo->prepare('SELECT path FROM mobile_vault_documents WHERE adherent_id = ?');
        $vault->execute([$memberId]);
        $files = array_map(fn($p) => $this->root . '/storage/uploads/' . $p, $vault->fetchAll(PDO::FETCH_COLUMN));
        $uploads = $this->pdo->prepare('SELECT id FROM mobile_uploads WHERE adherent_id = ?');
        $uploads->execute([$memberId]);
        foreach ($uploads->fetchAll(PDO::FETCH_COLUMN) as $uploadId) {
            $files[] = $this->root . '/storage/uploads/mobile-tmp/' . preg_replace('/[^a-f0-9]/', '', $uploadId) . '.part';
        }
        return $files;
    }
}
