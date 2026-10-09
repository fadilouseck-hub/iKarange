<?php

/**
 * Mobile API v1 — insured apps (Assur Plus iOS / Android).
 *
 * Contract: docs/api/openapi.yaml (paths relative to /api/mobile/v1). Bearer tokens (access 15 min,
 * refresh 30 days, rotated on every refresh), JSON in camelCase, errors as
 * { "error": { "code", "message", "fields" } } with messages in the request language (fr default, en).
 *
 * Built on the existing I'KARANGE tables (adherents, entreprises, baremes, prestataires, remboursements…)
 * plus the mobile_* tables from database/008_mobile_api.sql. Features the platform does not support yet
 * (SMS OTP, self-registration, online subscription and payments, OCR) answer 501/"failed" explicitly.
 */

const MOBILE_ACCESS_TTL = 900;
const MOBILE_REFRESH_TTL = 30 * 86400;
const MOBILE_QR_TTL = 60;
const MOBILE_UPLOAD_MAX = 10 * 1024 * 1024;
const MOBILE_CHUNK_SIZE = 256 * 1024;
const MOBILE_ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];

// ── Responses ───────────────────────────────────────────

function mLang(): string
{
    $header = strtolower($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'fr');
    return str_starts_with($header, 'en') ? 'en' : 'fr';
}

/** Picks the French or English message for the request. */
function t(string $fr, string $en): string
{
    return mLang() === 'en' ? $en : $fr;
}

function mJson(mixed $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function mEmpty(): never
{
    http_response_code(204);
    header('Cache-Control: no-store');
    exit;
}

function mError(int $status, string $code, string $message, array $fields = []): never
{
    mJson(['error' => ['code' => $code, 'message' => $message, 'fields' => (object)$fields]], $status);
}

function mNotAvailable(): never
{
    mError(501, 'not_available', t(
        "Cette fonctionnalité n'est pas encore disponible. Contactez votre gestionnaire.",
        'This feature is not available yet. Please contact your account manager.'));
}

function mBody(): array
{
    $data = json_decode(file_get_contents('php://input') ?: '', true);
    return is_array($data) ? $data : [];
}

function mIso(?string $value): ?string
{
    if (!$value) return null;
    $ts = strtotime($value);
    return $ts ? gmdate('Y-m-d\TH:i:s\Z', $ts) : null;
}

function mDay(?string $value): ?string
{
    if (!$value) return null;
    $ts = strtotime($value);
    return $ts ? date('Y-m-d', $ts) : null;
}

function mSecret(): ?string
{
    $secret = getenv('MOBILE_SECRET') ?: '';
    return strlen($secret) >= 32 ? $secret : null;
}

function mStatus(string $code, string $fr, string $en): array
{
    return ['code' => $code, 'label' => t($fr, $en)];
}

// ── Auth ────────────────────────────────────────────────

function mClientIp(): string
{
    return substr($_SERVER['REMOTE_ADDR'] ?? 'unknown', 0, 45);
}

function mRateLimited(): bool
{
    $cfg = $GLOBALS['appConfig']['rate_limit'];
    $stmt = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND attempted_at > (NOW() - INTERVAL ? SECOND)');
    $stmt->execute([mClientIp(), $cfg['login_window']]);
    return (int)$stmt->fetchColumn() >= $cfg['login_max'];
}

function mIssueTokens(int $adherentId): array
{
    $access = bin2hex(random_bytes(32));
    $refresh = bin2hex(random_bytes(32));
    db()->prepare('INSERT INTO mobile_tokens (adherent_id, access_hash, refresh_hash, access_expires_at, refresh_expires_at)
                   VALUES (?, ?, ?, FROM_UNIXTIME(?), FROM_UNIXTIME(?))')
        ->execute([$adherentId, hash('sha256', $access), hash('sha256', $refresh), time() + MOBILE_ACCESS_TTL, time() + MOBILE_REFRESH_TTL]);
    return ['accessToken' => $access, 'refreshToken' => $refresh, 'expiresIn' => MOBILE_ACCESS_TTL];
}

/**
 * The bearer credential. Shared hosting (Apache + CGI/FPM) often drops `Authorization`, or exposes it only as
 * REDIRECT_…HTTP_AUTHORIZATION after rewrites; the apps also send `X-Auth-Token`, which is always passed through.
 */
function mBearerHeader(): string
{
    foreach ($_SERVER as $key => $value) {
        if (is_string($value) && $value !== '' && preg_match('/^(REDIRECT_)*HTTP_AUTHORIZATION$/', $key)) return $value;
    }
    if (function_exists('getallheaders')) {
        foreach (getallheaders() as $name => $value) {
            if (strcasecmp($name, 'Authorization') === 0 && $value !== '') return $value;
        }
    }
    $token = $_SERVER['HTTP_X_AUTH_TOKEN'] ?? '';
    return $token !== '' ? 'Bearer ' . $token : '';
}

/** The authenticated adherent (active member), or a 401. */
function mAuth(): array
{
    $header = mBearerHeader();
    if (!preg_match('/^Bearer\s+([a-f0-9]{64})$/i', $header, $m)) {
        mError(401, 'unauthorized', t('Authentification requise.', 'Authentication required.'));
    }
    $stmt = db()->prepare('
        SELECT a.*, e.raison_sociale AS entreprise_nom, e.taux_prise_en_charge, e.date_adhesion AS entreprise_adhesion,
               o.name AS org_name
        FROM mobile_tokens t
        JOIN adherents a ON a.id = t.adherent_id
        LEFT JOIN entreprises e ON e.id = a.entreprise_id
        LEFT JOIN organizations o ON o.id = a.org_id
        WHERE t.access_hash = ? AND t.revoked_at IS NULL AND t.access_expires_at > NOW()
    ');
    $stmt->execute([hash('sha256', strtolower($m[1]))]);
    $adherent = $stmt->fetch();
    if (!$adherent || $adherent['statut'] === 'inactif') {
        mError(401, 'unauthorized', t('Session expirée.', 'Session expired.'));
    }
    return $adherent;
}

// ── Mapping ─────────────────────────────────────────────

function mPermissions(): array
{
    // Family, payments and online subscription are managed by the employer on this platform.
    return ['policy.view', 'card.view', 'claims.view', 'claims.create', 'vault.view', 'vault.manage'];
}

function mPhone(?string $raw): string
{
    $digits = preg_replace('/\D+/', '', (string)$raw);
    if (str_starts_with($digits, '00221')) $digits = substr($digits, 5);
    if (strlen($digits) === 12 && str_starts_with($digits, '221')) $digits = substr($digits, 3);
    return strlen($digits) === 9 ? '+221' . $digits : (string)$raw;
}

function mLanguage(int $adherentId): ?string
{
    $stmt = db()->prepare('SELECT language FROM mobile_preferences WHERE adherent_id = ?');
    $stmt->execute([$adherentId]);
    return $stmt->fetchColumn() ?: null;
}

function mMe(array $a): array
{
    return [
        'id' => 'adh_' . $a['id'],
        'firstName' => $a['prenom'],
        'lastName' => $a['nom'],
        'phone' => mPhone($a['telephone']),
        'email' => null,
        'birthDate' => mDay($a['date_naissance']),
        'gender' => $a['sexe'] === 'feminin' ? 'F' : ($a['sexe'] === 'masculin' ? 'M' : null),
        'address' => null,
        'city' => null,
        'photoURL' => null,
        'role' => 'principal',
        'permissions' => mPermissions(),
        'memberNumber' => $a['matricule'],
        'hasActivePolicy' => $a['statut'] === 'actif',
        'preferredLanguage' => mLanguage((int)$a['id']),
    ];
}

function mMemberStatus(string $statut): array
{
    return match ($statut) {
        'actif' => mStatus('active', 'Actif', 'Active'),
        'suspendu' => mStatus('suspended', 'Suspendu', 'Suspended'),
        default => mStatus('terminated', 'Inactif', 'Inactive'),
    };
}

function mPolicySummary(array $a): array
{
    return [
        'id' => 'ent_' . (int)$a['entreprise_id'],
        'number' => $a['matricule'],
        'productName' => $a['org_name'] ?: "I'KARANGE",
        'formulaName' => $a['entreprise_nom'] ?: t('Contrat entreprise', 'Company plan'),
        'startDate' => mDay($a['date_adhesion'] ?: $a['entreprise_adhesion']) ?? date('Y-01-01'),
        'endDate' => date('Y-12-31'),
        'status' => mMemberStatus($a['statut']),
        'coverageRate' => (int)round((float)($a['taux_prise_en_charge'] ?? 0)),
        'territoriality' => null,
    ];
}

/** Annual limit and consumption (direct-billing approvals + validated reimbursements) for the current year. */
function mLimits(array $a): ?array
{
    $annual = (int)($a['plafond_annuel'] ?? 0);
    if ($annual <= 0) return null;
    $id = (int)$a['id'];
    $stmt = db()->prepare("SELECT COALESCE(SUM(part_ipm), 0) FROM prises_en_charge
        WHERE adherent_id = ? AND YEAR(date_soins) = YEAR(CURDATE()) AND statut IN ('approuvee','facturee','reglee')");
    $stmt->execute([$id]);
    $pec = (int)$stmt->fetchColumn();
    $stmt = db()->prepare("SELECT COALESCE(SUM(montant_rembourse), 0) AS valide,
            COALESCE(SUM(CASE WHEN statut = 'paye' THEN montant_rembourse ELSE 0 END), 0) AS paye
        FROM remboursements WHERE adherent_id = ? AND YEAR(date_soins) = YEAR(CURDATE()) AND statut IN ('valide','paye')");
    $stmt->execute([$id]);
    $remb = $stmt->fetch();
    $consumed = $pec + (int)$remb['valide'];
    return ['annualLimit' => $annual, 'consumed' => $consumed, 'reimbursed' => (int)$remb['paye'], 'remaining' => max($annual - $consumed, 0)];
}

function mClaimTypes(): array
{
    return [
        ['code' => 'consultation', 'label' => t('Consultation', 'Consultation'), 'symbol' => 'stethoscope'],
        ['code' => 'pharmacie', 'label' => t('Pharmacie', 'Pharmacy'), 'symbol' => 'pills'],
        ['code' => 'analyse', 'label' => t('Analyses', 'Lab tests'), 'symbol' => 'testtube.2'],
        ['code' => 'imagerie', 'label' => t('Imagerie', 'Imaging'), 'symbol' => 'rays'],
        ['code' => 'hospitalisation', 'label' => t('Hospitalisation', 'Hospital stay'), 'symbol' => 'bed.double'],
        ['code' => 'chirurgie', 'label' => t('Chirurgie', 'Surgery'), 'symbol' => 'cross.case'],
        ['code' => 'dentaire', 'label' => t('Dentaire', 'Dental'), 'symbol' => 'mouth'],
        ['code' => 'optique', 'label' => t('Optique', 'Optical'), 'symbol' => 'eyeglasses'],
        ['code' => 'maternite', 'label' => t('Maternité', 'Maternity'), 'symbol' => 'figure.and.child.holdinghands'],
        ['code' => 'autre', 'label' => t('Autre', 'Other'), 'symbol' => 'doc.text'],
    ];
}

function mClaimTypeLabel(string $code): string
{
    foreach (mClaimTypes() as $type) {
        if ($type['code'] === $code) return $type['label'];
    }
    return $code;
}

function mClaimStatus(string $statut): string
{
    return match ($statut) {
        'soumis' => 'submitted',
        'en_cours' => 'in_review',
        'valide' => 'validated',
        'rejete' => 'rejected',
        'paye' => 'paid',
        default => 'submitted',
    };
}

function mClaimSummary(array $r, array $a): array
{
    return [
        'id' => 'r' . $r['id'],
        'number' => $r['numero'],
        'status' => mClaimStatus($r['statut']),
        'typeLabel' => mClaimTypeLabel((string)$r['type_acte']),
        'beneficiaryName' => trim($a['prenom'] . ' ' . $a['nom']),
        'providerName' => null,
        'amount' => (int)$r['montant'],
        'createdAt' => mIso($r['created_at']),
        'updatedAt' => mIso($r['updated_at'] ?: $r['created_at']),
    ];
}

function mClaim(array $r, array $a): array
{
    $statut = $r['statut'];
    $timeline = [['status' => 'submitted', 'date' => mIso($r['created_at']), 'message' => null]];
    if ($statut === 'en_cours') {
        $timeline[] = ['status' => 'in_review', 'date' => mIso($r['updated_at']), 'message' => null];
    }
    if (in_array($statut, ['valide', 'paye'], true)) {
        $timeline[] = ['status' => 'validated', 'date' => mIso($r['date_validation'] ?: $r['updated_at']), 'message' => $r['commentaire_admin'] ?: null];
    }
    if ($statut === 'paye') {
        $timeline[] = ['status' => 'paid', 'date' => mIso($r['date_paiement'] ?: $r['updated_at']), 'message' => null];
    }
    if ($statut === 'rejete') {
        $timeline[] = ['status' => 'rejected', 'date' => mIso($r['updated_at']), 'message' => $r['commentaire_admin'] ?: null];
    }

    $settlement = null;
    if (in_array($statut, ['valide', 'paye'], true) && (int)$r['montant'] > 0) {
        $billed = (int)$r['montant'];
        $insurer = (int)$r['montant_rembourse'];
        $settlement = [
            'billedAmount' => $billed, 'coveredAmount' => null, 'deductible' => null,
            'reimbursementRate' => (int)round($insurer * 100 / $billed),
            'insurerAmount' => $insurer, 'remainingAmount' => max($billed - $insurer, 0),
        ];
    }

    $stmt = db()->prepare('SELECT id, nom_fichier, created_at FROM remboursement_documents WHERE remboursement_id = ? ORDER BY id');
    $stmt->execute([$r['id']]);
    $documents = array_map(fn($d) => [
        'id' => 'doc_' . $d['id'], 'kind' => 'receipt', 'fileName' => $d['nom_fichier'], 'createdAt' => mIso($d['created_at']),
    ], $stmt->fetchAll());

    $fields = [
        ['key' => 'date', 'label' => t('Date des soins', 'Date of care'), 'value' => mDay($r['date_soins']) ?? '', 'confidence' => null],
        ['key' => 'act', 'label' => t('Acte', 'Service'), 'value' => mClaimTypeLabel((string)$r['type_acte']), 'confidence' => null],
        ['key' => 'total', 'label' => t('Montant total', 'Total amount'), 'value' => (string)(int)$r['montant'], 'confidence' => null],
    ];
    if (!empty($r['motif'])) {
        $fields[] = ['key' => 'motif', 'label' => t('Détails', 'Details'), 'value' => $r['motif'], 'confidence' => null];
    }

    return [
        'id' => 'r' . $r['id'], 'number' => $r['numero'], 'status' => mClaimStatus($statut),
        'typeCode' => $r['type_acte'], 'typeLabel' => mClaimTypeLabel((string)$r['type_acte']),
        'beneficiaryId' => 'adh_' . $a['id'], 'beneficiaryName' => trim($a['prenom'] . ' ' . $a['nom']),
        'createdAt' => mIso($r['created_at']), 'submittedAt' => mIso($r['created_at']),
        'fields' => $fields, 'lines' => [], 'documents' => $documents, 'timeline' => $timeline,
        'documentRequests' => [], 'settlement' => $settlement,
        'rejectionReason' => $statut === 'rejete' ? ($r['commentaire_admin'] ?: null) : null,
    ];
}

function mDraftClaim(array $d, array $a): array
{
    $fields = json_decode($d['fields'] ?? 'null', true) ?: [];
    $labels = [
        'invoiceNumber' => t('N° de facture', 'Invoice no.'), 'date' => t('Date', 'Date'), 'provider' => t('Prestataire', 'Provider'),
        'patient' => t('Patient', 'Patient'), 'act' => t('Acte', 'Service'), 'total' => t('Montant total', 'Total amount'),
    ];
    $uploads = json_decode($d['upload_ids'] ?? 'null', true) ?: [];
    return [
        'id' => 'd' . $d['id'], 'number' => null, 'status' => 'draft',
        'typeCode' => $d['type_acte'], 'typeLabel' => mClaimTypeLabel($d['type_acte']),
        'beneficiaryId' => 'adh_' . $a['id'], 'beneficiaryName' => trim($a['prenom'] . ' ' . $a['nom']),
        'createdAt' => mIso($d['created_at']), 'submittedAt' => null,
        'fields' => array_map(fn($k, $v) => ['key' => $k, 'label' => $labels[$k] ?? $k, 'value' => (string)$v, 'confidence' => null], array_keys($fields), $fields),
        'lines' => json_decode($d['lines'] ?? 'null', true) ?: [],
        'documents' => array_map(fn($u) => ['id' => 'upl_' . $u, 'kind' => 'receipt', 'fileName' => 'justificatif', 'createdAt' => mIso($d['created_at'])], $uploads),
        'timeline' => [['status' => 'draft', 'date' => mIso($d['created_at']), 'message' => null]],
        'documentRequests' => [], 'settlement' => null, 'rejectionReason' => null,
    ];
}

/**
 * Providers have no stored coordinates yet: place them near their city centre, with a small stable offset
 * per provider so pins don't overlap. Flagged as approximate; the app routes by address, not by this point.
 */
function mApproximateLocation(array $p): ?array
{
    $cities = [
        'dakar' => [14.6928, -17.4467], 'pikine' => [14.7550, -17.3900], 'guediawaye' => [14.7833, -17.4000],
        'rufisque' => [14.7167, -17.2667], 'thies' => [14.7910, -16.9359], 'mbour' => [14.4200, -16.9640],
        'saint-louis' => [16.0326, -16.4818], 'kaolack' => [14.1652, -16.0726], 'ziguinchor' => [12.5641, -16.2640],
        'touba' => [14.8500, -15.8833], 'diourbel' => [14.6550, -16.2314], 'tambacounda' => [13.7707, -13.6673],
        'nouakchott' => [18.0735, -15.9582], 'nouadhibou' => [20.9310, -17.0347],
    ];
    $key = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', trim((string)$p['ville'])) ?: '');
    $key = str_replace(' ', '-', $key);
    if (!isset($cities[$key])) return null;
    $seed = crc32('prestataire-' . $p['id']);
    $dLat = (($seed & 0xFFFF) / 0xFFFF - 0.5) * 0.04;          // about ±2 km
    $dLng = ((($seed >> 16) & 0xFFFF) / 0xFFFF - 0.5) * 0.04;
    return [round($cities[$key][0] + $dLat, 5), round($cities[$key][1] + $dLng, 5)];
}

function mProviderType(string $type): string
{
    return match ($type) {
        'clinique' => 'clinic', 'hopital' => 'hospital', 'pharmacie' => 'pharmacy', 'laboratoire' => 'laboratory',
        'dentiste' => 'specialist', default => 'other',
    };
}

// ── Uploads ─────────────────────────────────────────────

function mUploadDir(string $sub): string
{
    $dir = basePath('storage/uploads/' . $sub);
    if (!is_dir($dir)) mkdir($dir, 0750, true);
    return $dir;
}

function mUploadPath(string $id): string
{
    return mUploadDir('mobile-tmp') . '/' . $id . '.part';
}

/** A completed, unconsumed upload of the adherent, or an error. */
function mCompletedUpload(string $id, int $adherentId): array
{
    $stmt = db()->prepare('SELECT * FROM mobile_uploads WHERE id = ? AND adherent_id = ? AND consumed_at IS NULL');
    $stmt->execute([$id, $adherentId]);
    $upload = $stmt->fetch();
    if (!$upload || (int)$upload['received'] !== (int)$upload['size'] || !is_file(mUploadPath($id))) {
        mError(422, 'upload_missing', t('Fichier introuvable, veuillez réessayer.', 'File not found, please try again.'));
    }
    $mime = mime_content_type(mUploadPath($id)) ?: '';
    if (!in_array($mime, MOBILE_ALLOWED_MIME, true)) {
        mError(422, 'invalid_type', t('Type de fichier non autorisé (JPEG, PNG, PDF).', 'File type not allowed (JPEG, PNG, PDF).'));
    }
    $upload['detected_mime'] = $mime;
    return $upload;
}

function mConsumeUpload(string $id): void
{
    db()->prepare('UPDATE mobile_uploads SET consumed_at = NOW() WHERE id = ?')->execute([$id]);
}

/** Moves a completed upload into the reimbursement documents of a claim. */
function mAttachToRemboursement(array $upload, int $rembId): void
{
    $ext = $upload['detected_mime'] === 'application/pdf' ? 'pdf' : ($upload['detected_mime'] === 'image/png' ? 'png' : 'jpg');
    $stored = $rembId . '_' . time() . '_' . substr($upload['id'], 0, 8) . '.' . $ext;
    $dest = mUploadDir('remboursements') . '/' . $stored;
    if (!rename(mUploadPath($upload['id']), $dest)) {
        mError(500, 'storage_error', t("Le fichier n'a pas pu être enregistré.", 'The file could not be saved.'));
    }
    db()->prepare('INSERT INTO remboursement_documents (remboursement_id, nom_fichier, chemin, taille, type_mime) VALUES (?, ?, ?, ?, ?)')
        ->execute([$rembId, $upload['file_name'], 'remboursements/' . $stored, (int)$upload['size'], $upload['detected_mime']]);
    mConsumeUpload($upload['id']);
}

// ── Vault encryption (AES-256-GCM, key derived from MOBILE_SECRET) ───────────

function mVaultKey(): string
{
    $secret = mSecret();
    if (!$secret) {
        mError(503, 'vault_unavailable', t('Le coffre santé est momentanément indisponible.', 'The health vault is temporarily unavailable.'));
    }
    return hash_hmac('sha256', 'vault-v1', $secret, true);
}

function mEncrypt(string $plain): string
{
    $iv = random_bytes(12);
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', mVaultKey(), OPENSSL_RAW_DATA, $iv, $tag);
    return 'V1' . $iv . $tag . $cipher;
}

function mDecrypt(string $blob): ?string
{
    if (!str_starts_with($blob, 'V1') || strlen($blob) < 30) return null;
    $iv = substr($blob, 2, 12);
    $tag = substr($blob, 14, 16);
    $plain = openssl_decrypt(substr($blob, 30), 'aes-256-gcm', mVaultKey(), OPENSSL_RAW_DATA, $iv, $tag);
    return $plain === false ? null : $plain;
}

// ── Minimal text PDF (care network list) ────────────────

function mPdf(string $title, array $lines): string
{
    $enc = fn(string $s) => str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], iconv('UTF-8', 'Windows-1252//TRANSLIT', $s) ?: '');
    $pages = array_chunk($lines, 48);
    if (!$pages) $pages = [[]];
    $objects = [];
    $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
    $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
    $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
    $kids = [];
    $next = 5;
    foreach ($pages as $index => $pageLines) {
        $stream = "BT /F2 16 Tf 48 800 Td (" . $enc($title) . ") Tj ET\n";
        $y = 770;
        foreach ($pageLines as $line) {
            $stream .= "BT /F1 10 Tf 48 $y Td (" . $enc($line) . ") Tj ET\n";
            $y -= 15;
        }
        $stream .= "BT /F1 8 Tf 48 30 Td (" . $enc(($index + 1) . ' / ' . count($pages)) . ") Tj ET\n";
        $contentId = $next++;
        $pageId = $next++;
        $objects[$contentId] = "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "endstream";
        $objects[$pageId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents $contentId 0 R /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> >>";
        $kids[] = "$pageId 0 R";
    }
    $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($kids) . ' >>';
    ksort($objects);
    $pdf = "%PDF-1.4\n";
    $offsets = [];
    foreach ($objects as $id => $body) {
        $offsets[$id] = strlen($pdf);
        $pdf .= "$id 0 obj\n$body\nendobj\n";
    }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
    foreach ($offsets as $offset) $pdf .= sprintf("%010d 00000 n \n", $offset);
    $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
    return $pdf;
}

// ── Router ──────────────────────────────────────────────

function mobileRoute(string $path, string $method): never
{
    $rel = trim(substr($path, strlen('/api/mobile/v1')), '/');
    $parts = $rel === '' ? [] : explode('/', $rel);
    $ids = [];
    $static = ['auth', 'otp', 'send', 'verify', 'login', 'register', 'refresh', 'logout', 'password', 'reset', 'legal', 'documents',
        'me', 'dashboard', 'card', 'qr-token', 'wallet-pass', 'photo', 'notification-preferences', 'deletion-request', 'products',
        'health-questionnaire', 'quotes', 'policies', 'conditions.pdf', 'termination-requests', 'dependents', 'requests',
        'payment-methods', 'payments', 'claim-types', 'claims', 'ocr', 'submit', 'vault', 'file', 'providers', 'network.pdf',
        'notifications', 'read-all', 'read', 'devices', 'uploads'];
    $template = implode('/', array_map(function ($part) use ($static, &$ids) {
        if (in_array($part, $static, true)) return $part;
        $ids[] = $part;
        return ':id';
    }, $parts));

    try {
        // All mobile timestamps are UTC (the app converts to the device time zone).
        db()->exec("SET time_zone = '+00:00'");
        mobileDispatch("$method $template", $ids);
    } catch (PDOException $e) {
        error_log('mobile api: ' . $e->getMessage());
        mError(500, 'server_error', t('Le service est momentanément indisponible. Veuillez réessayer.', 'The service is temporarily unavailable. Please try again.'));
    }
    mError(404, 'not_found', t('Élément introuvable.', 'Not found.'));
}

function mobileDispatch(string $route, array $ids): void
{
    switch ($route) {
        // ── Auth ──
        case 'POST auth/login':
            if (mRateLimited()) {
                mError(429, 'too_many_attempts', t('Trop de tentatives. Réessayez dans 15 minutes.', 'Too many attempts. Try again in 15 minutes.'));
            }
            $in = mBody();
            $identifier = trim((string)($in['identifier'] ?? $in['phone'] ?? ''));
            $password = (string)($in['password'] ?? '');
            if ($identifier === '' || $password === '') {
                if (!empty($in['verificationToken'])) mNotAvailable();
                mError(422, 'missing_credentials', t('Identifiant et mot de passe requis.', 'Username and password are required.'));
            }
            $stmt = db()->prepare("SELECT * FROM adherents WHERE login = ? AND login IS NOT NULL AND statut <> 'inactif' LIMIT 1");
            $stmt->execute([$identifier]);
            $adherent = $stmt->fetch();
            if (!$adherent || !$adherent['password_hash'] || !password_verify($password, $adherent['password_hash'])) {
                db()->prepare('INSERT INTO login_attempts (ip_address) VALUES (?)')->execute([mClientIp()]);
                mError(401, 'invalid_credentials', t('Identifiant ou mot de passe incorrect.', 'Incorrect username or password.'));
            }
            $stmt = db()->prepare('SELECT a.*, e.raison_sociale AS entreprise_nom, e.taux_prise_en_charge, o.name AS org_name
                FROM adherents a LEFT JOIN entreprises e ON e.id = a.entreprise_id LEFT JOIN organizations o ON o.id = a.org_id WHERE a.id = ?');
            $stmt->execute([$adherent['id']]);
            mJson(['tokens' => mIssueTokens((int)$adherent['id']), 'user' => mMe($stmt->fetch())]);

        case 'POST auth/refresh':
            $token = (string)(mBody()['refreshToken'] ?? '');
            $stmt = db()->prepare('SELECT * FROM mobile_tokens WHERE refresh_hash = ?');
            $stmt->execute([hash('sha256', $token)]);
            $row = $stmt->fetch();
            if (!$row || $row['revoked_at'] || strtotime($row['refresh_expires_at']) < time()) {
                if ($row && $row['revoked_at']) {
                    // Reuse of a rotated refresh token: revoke every session of this member.
                    db()->prepare('UPDATE mobile_tokens SET revoked_at = NOW() WHERE adherent_id = ? AND revoked_at IS NULL')->execute([$row['adherent_id']]);
                }
                mError(401, 'invalid_refresh', t('Session expirée.', 'Session expired.'));
            }
            db()->prepare('UPDATE mobile_tokens SET revoked_at = NOW() WHERE id = ?')->execute([$row['id']]);
            mJson(mIssueTokens((int)$row['adherent_id']));

        case 'POST auth/logout':
            $token = (string)(mBody()['refreshToken'] ?? '');
            db()->prepare('UPDATE mobile_tokens SET revoked_at = NOW() WHERE refresh_hash = ? AND revoked_at IS NULL')->execute([hash('sha256', $token)]);
            mEmpty();

        case 'POST auth/otp/send':
        case 'POST auth/otp/verify':
        case 'POST auth/register':
        case 'POST auth/password/reset':
            mNotAvailable();

        case 'GET legal/documents':
            mJson([]);

        // ── Profile ──
        case 'GET me':
            mJson(mMe(mAuth()));

        case 'PATCH me':
            $a = mAuth();
            $in = mBody();
            if (isset($in['preferredLanguage']) && in_array($in['preferredLanguage'], ['fr', 'en'], true)) {
                db()->prepare('INSERT INTO mobile_preferences (adherent_id, language) VALUES (?, ?) ON DUPLICATE KEY UPDATE language = VALUES(language)')
                    ->execute([$a['id'], $in['preferredLanguage']]);
            }
            mJson(mMe($a));

        case 'POST me/password':
            $a = mAuth();
            $in = mBody();
            $new = (string)($in['newPassword'] ?? '');
            if (!password_verify((string)($in['currentPassword'] ?? ''), (string)$a['password_hash'])) {
                mError(422, 'invalid_password', t('Le mot de passe actuel est incorrect.', 'The current password is incorrect.'),
                    ['currentPassword' => t('Mot de passe incorrect.', 'Incorrect password.')]);
            }
            if (mb_strlen($new) < 8) {
                mError(422, 'weak_password', t('8 caractères minimum.', 'At least 8 characters.'), ['newPassword' => t('8 caractères minimum.', 'At least 8 characters.')]);
            }
            db()->prepare('UPDATE adherents SET password_hash = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_BCRYPT), $a['id']]);
            mEmpty();

        case 'GET me/notification-preferences':
            $a = mAuth();
            $stmt = db()->prepare('SELECT notifications FROM mobile_preferences WHERE adherent_id = ?');
            $stmt->execute([$a['id']]);
            $saved = json_decode((string)$stmt->fetchColumn(), true);
            mJson($saved ?: ['categories' => [
                ['id' => 'claims', 'label' => t('Remboursements', 'Reimbursements'), 'push' => true, 'sms' => false, 'email' => false],
                ['id' => 'contract', 'label' => t('Contrat', 'Policy'), 'push' => true, 'sms' => false, 'email' => false],
            ]]);

        case 'PUT me/notification-preferences':
            $a = mAuth();
            $in = mBody();
            if (!isset($in['categories']) || !is_array($in['categories'])) mError(400, 'bad_request', t('Requête invalide.', 'Invalid request.'));
            db()->prepare('INSERT INTO mobile_preferences (adherent_id, notifications) VALUES (?, ?) ON DUPLICATE KEY UPDATE notifications = VALUES(notifications)')
                ->execute([$a['id'], json_encode($in, JSON_UNESCAPED_UNICODE)]);
            mJson($in);

        case 'POST me/deletion-request':
            $a = mAuth();
            db()->prepare('INSERT INTO mobile_deletion_requests (adherent_id, reason) VALUES (?, ?)')->execute([$a['id'], (string)(mBody()['reason'] ?? '')]);
            mJson(mStatus('requested', 'Demande enregistrée — votre gestionnaire vous contactera', 'Request recorded — your account manager will contact you'));

        case 'PUT me/photo':
            mNotAvailable();

        // ── Dashboard & card ──
        case 'GET me/dashboard':
            $a = mAuth();
            $stmt = db()->prepare('SELECT * FROM remboursements WHERE adherent_id = ? ORDER BY created_at DESC LIMIT 3');
            $stmt->execute([$a['id']]);
            $alerts = [];
            if ($a['statut'] === 'suspendu') {
                $alerts[] = ['level' => 'warning', 'text' => t('Votre couverture est suspendue. Contactez votre gestionnaire.', 'Your coverage is suspended. Please contact your account manager.')];
            }
            mJson([
                'fullName' => trim($a['prenom'] . ' ' . $a['nom']),
                'memberNumber' => null,
                'policy' => mPolicySummary($a),
                'limits' => mLimits($a),
                'dependents' => [],
                'recentClaims' => array_map(fn($r) => mClaimSummary($r, $a), $stmt->fetchAll()),
                'unreadNotifications' => 0,
                'alerts' => $alerts,
            ]);

        case 'GET me/card':
            $a = mAuth();
            mJson(['beneficiaries' => [[
                'id' => 'adh_' . $a['id'],
                'fullName' => trim($a['prenom'] . ' ' . $a['nom']),
                'relationLabel' => t('Assuré(e) principal(e)', 'Main policyholder'),
                'memberNumber' => $a['matricule'],
                'policyNumber' => $a['entreprise_nom'] ?: '',
                'insurerName' => $a['org_name'] ?: "I'KARANGE",
                'formulaName' => $a['entreprise_nom'] ?: '',
                'coverageRate' => (int)round((float)($a['taux_prise_en_charge'] ?? 0)),
                'validUntil' => date('Y-12-31'),
                'photoURL' => null,
                'status' => mMemberStatus($a['statut']),
                'birthDate' => mDay($a['date_naissance']),
            ]]]);

        case 'GET me/card/qr-token':
            $a = mAuth();
            $token = 'IK1.' . rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
            $expires = time() + MOBILE_QR_TTL;
            db()->prepare('DELETE FROM mobile_qr_tokens WHERE expires_at < NOW() - INTERVAL 1 DAY')->execute();
            db()->prepare('INSERT INTO mobile_qr_tokens (adherent_id, token_hash, expires_at) VALUES (?, ?, FROM_UNIXTIME(?))')
                ->execute([$a['id'], hash('sha256', $token), $expires]);
            mJson(['token' => $token, 'expiresAt' => gmdate('Y-m-d\TH:i:s\Z', $expires)]);

        case 'GET me/card/wallet-pass':
            $a = mAuth();
            require_once basePath('src/core/AppleWallet.php');
            try {
                $pkpass = (new AppleWallet())->generate($a);
            } catch (Throwable $e) {
                error_log('mobile wallet: ' . $e->getMessage());
                mError(503, 'wallet_unavailable', t("La carte Wallet n'est pas disponible pour le moment.", 'The Wallet card is not available right now.'));
            }
            header('Content-Type: application/vnd.apple.pkpass');
            header('Cache-Control: no-store');
            echo $pkpass;
            exit;

        // ── Contract ──
        case 'GET policies/:id':
            $a = mAuth();
            if ($ids[0] !== 'ent_' . (int)$a['entreprise_id']) mError(404, 'not_found', t('Élément introuvable.', 'Not found.'));
            $stmt = db()->prepare('SELECT * FROM baremes WHERE entreprise_id = ? AND org_id = ? AND is_active = 1 ORDER BY libelle');
            $stmt->execute([$a['entreprise_id'], $a['org_id']]);
            $period = fn($p) => match ($p) {
                'par_evenement' => t('par acte', 'per service'),
                'par_2_ans' => t('tous les 2 ans', 'every 2 years'),
                default => t('par an', 'per year'),
            };
            $guarantees = array_map(fn($b) => [
                'code' => (string)$b['type_acte'],
                'label' => $b['libelle'],
                'limitLabel' => $b['plafond_acte'] ? t('Plafond : ', 'Limit: ') . number_format((int)$b['plafond_acte'], 0, ',', ' ') . ' FCFA ' . $period($b['periode_plafond']) : null,
                'rateLabel' => (int)round((float)$b['taux_couverture']) . ' %',
                'description' => null,
            ], $stmt->fetchAll());
            mJson([
                'summary' => mPolicySummary($a),
                'insurerName' => $a['org_name'] ?: "I'KARANGE",
                'premiumLabel' => null,
                'members' => [['id' => 'adh_' . $a['id'], 'fullName' => trim($a['prenom'] . ' ' . $a['nom']),
                    'relationLabel' => t('Assuré(e) principal(e)', 'Main policyholder'), 'birthDate' => mDay($a['date_naissance'])]],
                'guarantees' => $guarantees,
                'exclusions' => [], 'waitingPeriods' => [], 'deductibleLabel' => null,
                'renewal' => null, 'conditionsVersion' => null, 'pendingTermination' => null,
            ]);

        case 'GET policies/:id/conditions.pdf':
            mAuth();
            mError(404, 'not_available', t('Les conditions particulières ne sont pas encore disponibles en ligne.', 'The policy conditions are not available online yet.'));

        case 'POST policies/:id/termination-requests':
        case 'GET health-questionnaire':
        case 'POST quotes':
        case 'POST policies':
        case 'POST payments':
        case 'GET payments/:id':
        case 'POST dependents/requests':
        case 'DELETE dependents/:id':
            mAuth();
            mNotAvailable();

        case 'GET products':
        case 'GET payment-methods':
        case 'GET payments':
            mAuth();
            mJson([]);

        case 'GET dependents':
            mAuth();
            mJson(['dependents' => [], 'requests' => [], 'canRequestChanges' => false,
                'info' => t('Les ayants droit sont gérés par votre entreprise.', 'Dependants are managed by your employer.')]);

        // ── Claims ──
        case 'GET claim-types':
            mAuth();
            mJson(array_map(fn($t) => $t + ['requiredDocuments' => [t('Facture ou reçu', 'Invoice or receipt')]], mClaimTypes()));

        case 'GET claims':
            $a = mAuth();
            $stmt = db()->prepare('SELECT * FROM remboursements WHERE adherent_id = ? ORDER BY created_at DESC');
            $stmt->execute([$a['id']]);
            mJson(array_map(fn($r) => mClaimSummary($r, $a), $stmt->fetchAll()));

        case 'POST claims':
            $a = mAuth();
            $in = mBody();
            $type = (string)($in['typeCode'] ?? '');
            if (!in_array($type, array_column(mClaimTypes(), 'code'), true)) mError(422, 'invalid_type', t("Type d'acte invalide.", 'Invalid type of care.'));
            if (($in['beneficiaryId'] ?? '') !== 'adh_' . $a['id']) {
                mError(403, 'forbidden', t('Vous ne pouvez pas déclarer pour ce bénéficiaire.', 'You cannot submit a claim for this beneficiary.'));
            }
            db()->prepare('INSERT INTO mobile_claim_drafts (adherent_id, type_acte) VALUES (?, ?)')->execute([$a['id'], $type]);
            $stmt = db()->prepare('SELECT * FROM mobile_claim_drafts WHERE id = ?');
            $stmt->execute([db()->lastInsertId()]);
            mJson(mDraftClaim($stmt->fetch(), $a), 201);

        case 'GET claims/:id':
        case 'PATCH claims/:id':
        case 'POST claims/:id/documents':
        case 'GET claims/:id/ocr':
        case 'POST claims/:id/submit':
            mobileClaim(substr($route, 0, strpos($route, ' ')), $route, $ids[0], mAuth());

        // ── Vault ──
        case 'GET vault/documents':
            $a = mAuth();
            $category = $_GET['category'] ?? null;
            $sql = 'SELECT * FROM mobile_vault_documents WHERE adherent_id = ?' . ($category ? ' AND category = ?' : '') . ' ORDER BY created_at DESC';
            $stmt = db()->prepare($sql);
            $stmt->execute($category ? [$a['id'], $category] : [$a['id']]);
            mJson(array_map(fn($d) => [
                'id' => 'v' . $d['id'], 'category' => $d['category'], 'title' => $d['title'], 'fileName' => $d['file_name'],
                'mimeType' => $d['mime_type'], 'size' => (int)$d['size'], 'createdAt' => mIso($d['created_at']),
                'beneficiaryName' => trim($a['prenom'] . ' ' . $a['nom']),
            ], $stmt->fetchAll()));

        case 'POST vault/documents':
            $a = mAuth();
            $in = mBody();
            $category = (string)($in['category'] ?? '');
            $title = trim((string)($in['title'] ?? ''));
            if (!in_array($category, ['prescription', 'lab_result', 'imaging', 'vaccine'], true) || $title === '') {
                mError(422, 'invalid_document', t('Titre et catégorie requis.', 'Title and category are required.'));
            }
            $upload = mCompletedUpload((string)($in['uploadId'] ?? ''), (int)$a['id']);
            $relative = 'coffre/' . $a['id'] . '_' . bin2hex(random_bytes(8)) . '.bin';
            mUploadDir('coffre');
            file_put_contents(basePath('storage/uploads/' . $relative), mEncrypt((string)file_get_contents(mUploadPath($upload['id']))));
            @unlink(mUploadPath($upload['id']));
            mConsumeUpload($upload['id']);
            db()->prepare('INSERT INTO mobile_vault_documents (adherent_id, category, title, file_name, mime_type, size, path) VALUES (?, ?, ?, ?, ?, ?, ?)')
                ->execute([$a['id'], $category, mb_substr($title, 0, 255), $upload['file_name'], $upload['detected_mime'], (int)$upload['size'], $relative]);
            $id = (int)db()->lastInsertId();
            mJson(['id' => 'v' . $id, 'category' => $category, 'title' => $title, 'fileName' => $upload['file_name'], 'mimeType' => $upload['detected_mime'],
                'size' => (int)$upload['size'], 'createdAt' => gmdate('Y-m-d\TH:i:s\Z'), 'beneficiaryName' => trim($a['prenom'] . ' ' . $a['nom'])], 201);

        case 'GET vault/documents/:id/file':
        case 'DELETE vault/documents/:id':
            $a = mAuth();
            $stmt = db()->prepare('SELECT * FROM mobile_vault_documents WHERE id = ? AND adherent_id = ?');
            $stmt->execute([(int)ltrim($ids[0], 'v'), $a['id']]);
            $doc = $stmt->fetch();
            if (!$doc) mError(404, 'not_found', t('Élément introuvable.', 'Not found.'));
            $file = basePath('storage/uploads/' . $doc['path']);
            if (str_starts_with($route, 'DELETE')) {
                @unlink($file);
                db()->prepare('DELETE FROM mobile_vault_documents WHERE id = ?')->execute([$doc['id']]);
                mEmpty();
            }
            $plain = is_file($file) ? mDecrypt((string)file_get_contents($file)) : null;
            if ($plain === null) mError(500, 'storage_error', t('Le document est illisible.', 'The document cannot be read.'));
            header('Content-Type: ' . $doc['mime_type']);
            header('Cache-Control: no-store');
            echo $plain;
            exit;

        // ── Care network ──
        case 'GET providers':
        case 'GET providers/network.pdf':
            $a = mAuth();
            $where = ["org_id = ?", "statut = 'agree'"];
            $params = [$a['org_id']];
            $typeMap = ['clinic' => ['clinique'], 'hospital' => ['hopital'], 'pharmacy' => ['pharmacie'], 'laboratory' => ['laboratoire'],
                'specialist' => ['dentiste'], 'other' => ['centre_imagerie']];
            if (!empty($_GET['type'])) {
                $types = $typeMap[$_GET['type']] ?? ['__none__'];
                $where[] = 'type IN (' . implode(',', array_fill(0, count($types), '?')) . ')';
                array_push($params, ...$types);
            }
            if (!empty($_GET['q'])) {
                $where[] = '(nom LIKE ? OR ville LIKE ? OR specialites LIKE ?)';
                $like = '%' . $_GET['q'] . '%';
                array_push($params, $like, $like, $like);
            }
            $stmt = db()->prepare('SELECT * FROM prestataires WHERE ' . implode(' AND ', $where) . ' ORDER BY nom');
            $stmt->execute($params);
            $rows = $stmt->fetchAll();
            if (str_ends_with($route, 'network.pdf')) {
                $lines = [];
                foreach ($rows as $p) {
                    $lines[] = $p['nom'] . ' — ' . $p['ville'] . ($p['telephone'] ? ' — ' . $p['telephone'] : '');
                    if ($p['adresse']) $lines[] = '    ' . preg_replace('/\s+/', ' ', $p['adresse']);
                }
                header('Content-Type: application/pdf');
                header('Cache-Control: no-store');
                echo mPdf(t('Réseau de soins conventionné', 'Contracted care network'), $lines);
                exit;
            }
            $lat = isset($_GET['lat']) ? (float)$_GET['lat'] : null;
            $lng = isset($_GET['lng']) ? (float)$_GET['lng'] : null;
            $radius = isset($_GET['radius']) ? (float)$_GET['radius'] * 1000 : null;
            $providers = [];
            foreach ($rows as $p) {
                $location = mApproximateLocation($p);
                $distance = null;
                if ($location && $lat !== null && $lng !== null) {
                    $dLat = deg2rad($location[0] - $lat);
                    $dLng = deg2rad($location[1] - $lng);
                    $h = sin($dLat / 2) ** 2 + cos(deg2rad($lat)) * cos(deg2rad($location[0])) * sin($dLng / 2) ** 2;
                    $distance = (int)(6371000 * 2 * atan2(sqrt($h), sqrt(1 - $h)));
                    if ($radius !== null && $distance > $radius) continue;
                }
                $providers[] = [
                    'id' => 'p' . $p['id'], 'name' => $p['nom'], 'type' => mProviderType((string)$p['type']),
                    'specialty' => $p['specialites'] ?: null, 'address' => preg_replace('/\s+/', ' ', (string)$p['adresse']),
                    'city' => (string)$p['ville'], 'phone' => $p['telephone'] ?: null,
                    'latitude' => $location[0] ?? null, 'longitude' => $location[1] ?? null, 'locationApproximate' => $location !== null,
                    'distanceMeters' => $distance, 'tiersPayant' => true, 'openingHours' => null,
                ];
            }
            if ($lat !== null) usort($providers, fn($x, $y) => ($x['distanceMeters'] ?? PHP_INT_MAX) <=> ($y['distanceMeters'] ?? PHP_INT_MAX));
            mJson(['providers' => $providers]);

        // ── Notifications & devices ──
        case 'GET notifications':
            mAuth();
            mJson(['notifications' => [], 'unreadCount' => 0]);

        case 'POST notifications/read-all':
        case 'POST notifications/:id/read':
            mAuth();
            mEmpty();

        case 'POST devices':
            $a = mAuth();
            $in = mBody();
            $token = substr((string)($in['token'] ?? ''), 0, 255);
            if ($token === '') mError(400, 'bad_request', t('Requête invalide.', 'Invalid request.'));
            db()->prepare('INSERT INTO mobile_devices (adherent_id, token, platform, locale, app_version) VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE adherent_id = VALUES(adherent_id), locale = VALUES(locale), app_version = VALUES(app_version)')
                ->execute([$a['id'], $token, substr((string)($in['platform'] ?? 'ios'), 0, 20), substr((string)($in['locale'] ?? ''), 0, 20), substr((string)($in['appVersion'] ?? ''), 0, 20)]);
            mEmpty();

        // ── Resumable uploads ──
        case 'POST uploads':
            $a = mAuth();
            $in = mBody();
            $size = (int)($in['size'] ?? 0);
            if ($size <= 0 || $size > MOBILE_UPLOAD_MAX) mError(413, 'too_large', t('Le fichier dépasse 10 Mo.', 'The file exceeds 10 MB.'));
            if (!in_array($in['mimeType'] ?? '', MOBILE_ALLOWED_MIME, true)) {
                mError(422, 'invalid_type', t('Type de fichier non autorisé (JPEG, PNG, PDF).', 'File type not allowed (JPEG, PNG, PDF).'));
            }
            $id = bin2hex(random_bytes(16));
            db()->prepare('INSERT INTO mobile_uploads (id, adherent_id, file_name, mime_type, size, purpose) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$id, $a['id'], mb_substr(basename((string)($in['fileName'] ?? 'document')), 0, 255), $in['mimeType'], $size, substr((string)($in['purpose'] ?? ''), 0, 50)]);
            touch(mUploadPath($id));
            mJson(['uploadId' => $id, 'chunkSize' => MOBILE_CHUNK_SIZE, 'offset' => 0], 201);

        case 'GET uploads/:id':
        case 'PATCH uploads/:id':
            $a = mAuth();
            $stmt = db()->prepare('SELECT * FROM mobile_uploads WHERE id = ? AND adherent_id = ? AND consumed_at IS NULL');
            $stmt->execute([preg_replace('/[^a-f0-9]/', '', $ids[0]), $a['id']]);
            $upload = $stmt->fetch();
            if (!$upload) mError(404, 'not_found', t('Élément introuvable.', 'Not found.'));
            $path = mUploadPath($upload['id']);
            clearstatcache();
            $received = is_file($path) ? filesize($path) : 0;
            if (str_starts_with($route, 'PATCH')) {
                $offset = (int)($_SERVER['HTTP_UPLOAD_OFFSET'] ?? -1);
                if ($offset !== $received) {
                    mError(409, 'offset_mismatch', t('Reprise du téléversement…', 'Resuming upload…'));
                }
                $chunk = file_get_contents('php://input') ?: '';
                if ($received + strlen($chunk) > (int)$upload['size']) mError(413, 'too_large', t('Fichier trop volumineux.', 'File too large.'));
                file_put_contents($path, $chunk, FILE_APPEND | LOCK_EX);
                $received += strlen($chunk);
                db()->prepare('UPDATE mobile_uploads SET received = ? WHERE id = ?')->execute([$received, $upload['id']]);
            }
            mJson(['uploadId' => $upload['id'], 'chunkSize' => MOBILE_CHUNK_SIZE, 'offset' => $received]);
    }
}

/** Claim operations: drafts ("d<id>", app-side wizard) and submitted reimbursements ("r<id>"). */
function mobileClaim(string $method, string $route, string $claimId, array $a): never
{
    if (str_starts_with($claimId, 'r')) {
        $stmt = db()->prepare('SELECT * FROM remboursements WHERE id = ? AND adherent_id = ?');
        $stmt->execute([(int)substr($claimId, 1), $a['id']]);
        $remb = $stmt->fetch();
        if (!$remb) mError(404, 'not_found', t('Élément introuvable.', 'Not found.'));
        if ($route === 'GET claims/:id' || $route === 'POST claims/:id/submit') mJson(mClaim($remb, $a));
        if ($route === 'POST claims/:id/documents') {
            $upload = mCompletedUpload((string)(mBody()['uploadId'] ?? ''), (int)$a['id']);
            mAttachToRemboursement($upload, (int)$remb['id']);
            mJson(['id' => 'doc_new', 'kind' => 'additional', 'fileName' => $upload['file_name'], 'createdAt' => gmdate('Y-m-d\TH:i:s\Z')], 201);
        }
        mError(409, 'not_editable', t('Ce sinistre ne peut plus être modifié.', 'This claim can no longer be changed.'));
    }

    $stmt = db()->prepare('SELECT * FROM mobile_claim_drafts WHERE id = ? AND adherent_id = ?');
    $stmt->execute([(int)substr($claimId, 1), $a['id']]);
    $draft = $stmt->fetch();
    if (!$draft) mError(404, 'not_found', t('Élément introuvable.', 'Not found.'));

    switch ($route) {
        case 'GET claims/:id':
            mJson(mDraftClaim($draft, $a));

        case 'GET claims/:id/ocr':
            // No server OCR on this platform yet: the app switches to manual entry.
            mJson(['state' => 'failed', 'progress' => null, 'fields' => [], 'lines' => [], 'reviewThreshold' => 0.85,
                'message' => t('Lecture automatique indisponible : saisissez les informations.', 'Automatic reading unavailable: please enter the details.')]);

        case 'POST claims/:id/documents':
            $upload = mCompletedUpload((string)(mBody()['uploadId'] ?? ''), (int)$a['id']);
            $uploads = json_decode($draft['upload_ids'] ?? 'null', true) ?: [];
            if (count($uploads) >= 10) mError(422, 'too_many_files', t('Maximum 10 fichiers par demande.', 'Maximum 10 files per claim.'));
            $uploads[] = $upload['id'];
            db()->prepare('UPDATE mobile_claim_drafts SET upload_ids = ? WHERE id = ?')->execute([json_encode($uploads), $draft['id']]);
            mJson(['id' => 'upl_' . $upload['id'], 'kind' => 'receipt', 'fileName' => $upload['file_name'], 'createdAt' => gmdate('Y-m-d\TH:i:s\Z')], 201);

        case 'PATCH claims/:id':
            $in = mBody();
            $fields = array_map('strval', array_filter((array)($in['fields'] ?? []), 'is_scalar'));
            db()->prepare('UPDATE mobile_claim_drafts SET `fields` = ?, `lines` = ? WHERE id = ?')
                ->execute([json_encode($fields, JSON_UNESCAPED_UNICODE), json_encode(array_values((array)($in['lines'] ?? [])), JSON_UNESCAPED_UNICODE), $draft['id']]);
            $stmt = db()->prepare('SELECT * FROM mobile_claim_drafts WHERE id = ?');
            $stmt->execute([$draft['id']]);
            mJson(mDraftClaim($stmt->fetch(), $a));

        case 'POST claims/:id/submit':
            $fields = json_decode($draft['fields'] ?? 'null', true) ?: [];
            $lines = json_decode($draft['lines'] ?? 'null', true) ?: [];
            $uploads = json_decode($draft['upload_ids'] ?? 'null', true) ?: [];
            $amount = (int)preg_replace('/\D+/', '', (string)($fields['total'] ?? ''));
            $dateRaw = trim((string)($fields['date'] ?? ''));
            $date = null;
            foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y'] as $format) {
                $parsed = DateTime::createFromFormat('!' . $format, $dateRaw);
                if ($parsed && $parsed->format($format) === $dateRaw) { $date = $parsed->format('Y-m-d'); break; }
            }
            $errors = [];
            if ($amount <= 0) $errors['total'] = t('Montant requis.', 'Amount required.');
            if (!$date || $date > date('Y-m-d')) $errors['date'] = t('Date des soins invalide (JJ/MM/AAAA).', 'Invalid date of care (DD/MM/YYYY).');
            if (!$uploads) $errors['documents'] = t('Ajoutez au moins un justificatif.', 'Add at least one supporting document.');
            if ($errors) mError(422, 'invalid_claim', reset($errors), $errors);

            $details = array_filter([
                !empty($fields['invoiceNumber']) ? t('Facture ', 'Invoice ') . $fields['invoiceNumber'] : null,
                !empty($fields['provider']) ? t('Prestataire : ', 'Provider: ') . $fields['provider'] : null,
                !empty($fields['patient']) ? t('Patient : ', 'Patient: ') . $fields['patient'] : null,
                !empty($fields['act']) ? $fields['act'] : null,
            ]);
            foreach ($lines as $line) {
                if (!empty($line['label'])) $details[] = '- ' . $line['label'] . ' x' . ($line['quantity'] ?? '1') . (isset($line['amount']) && $line['amount'] !== '' ? ' = ' . $line['amount'] . ' FCFA' : '');
            }

            $pdo = db();
            $pdo->beginTransaction();
            $numero = generateNumero('RMB');
            $pdo->prepare("INSERT INTO remboursements (org_id, adherent_id, numero, type_acte, date_soins, montant, motif, statut) VALUES (?, ?, ?, ?, ?, ?, ?, 'soumis')")
                ->execute([$a['org_id'], $a['id'], $numero, $draft['type_acte'], $date, $amount, mb_substr(implode("\n", $details), 0, 5000)]);
            $rembId = (int)$pdo->lastInsertId();
            foreach ($uploads as $uploadId) {
                mAttachToRemboursement(mCompletedUpload((string)$uploadId, (int)$a['id']), $rembId);
            }
            $pdo->prepare('DELETE FROM mobile_claim_drafts WHERE id = ?')->execute([$draft['id']]);
            $pdo->commit();

            $stmt = db()->prepare('SELECT * FROM remboursements WHERE id = ?');
            $stmt->execute([$rembId]);
            mJson(mClaim($stmt->fetch(), $a));
    }
    mError(404, 'not_found', t('Élément introuvable.', 'Not found.'));
}
